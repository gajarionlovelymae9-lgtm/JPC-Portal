<?php

namespace App\Services\AI;

use App\Enums\AiMessageRole;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\AI\Prompts\AiChatSystemPrompt;
use App\Services\AiReports\Ai\Contracts\AiProvider;
use App\Services\AiReports\Ai\Contracts\AiRequest;
use App\Services\AiReports\Ai\Exceptions\AiProviderConfigurationException;
use App\Services\AiReports\Ai\Exceptions\AiProviderException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Conversational employee JPC AIDE assistant.
 *
 * Uses the configured AiProvider for multi-turn assistance (accomplishment
 * summaries, report writing, wording/grammar, organizing tasks/challenges/
 * next plans) grounded in what the employee actually provides. It never
 * fabricates activities, accomplishments, or performance data.
 *
 * Multilingual: follows the user's message language (auto-detect) unless an
 * explicit language preference is set (English, Filipino/Tagalog, Taglish).
 */
class EmployeeAiAssistantService
{
    private const HISTORY_LIMIT = 16;
    private const MAX_HISTORY_CHARS = 12000;
    private const ALLOWED_LANGUAGES = ['auto', 'english', 'filipino', 'tagalog', 'taglish'];

    /**
     * Titles a conversation may still carry before the employee's first message
     * replaces them. Anything else is treated as a user-chosen title.
     */
    private const PLACEHOLDER_TITLES = ['', 'new chat', 'new conversation', 'untitled', 'untitled conversation'];

    public function __construct(
        private readonly AiProvider $provider,
        private readonly ?AiChatSystemPrompt $systemPrompt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function chat(User $user, ?int $conversationId, string $message, ?string $languagePreference = null): array
    {
        $language = $this->resolveLanguage($user, $message, $languagePreference);
        $conversation = $this->resolveConversation($user, $conversationId, $message);

        $userMessage = $conversation->messages()->create([
            'role' => AiMessageRole::User,
            'content' => $message,
            'is_visible' => true,
        ]);

        $conversation->forceFill(['updated_at' => now()])->save();

        try {
            $assistantText = $this->generateReply($user, $conversation, $message, $language);
            $assistantMessage = $conversation->messages()->create([
                'role' => AiMessageRole::Assistant,
                'content' => $assistantText,
                'intent' => 'employee_assistant',
                'tool_results' => ['provider' => $this->provider->name(), 'language' => $language],
                'is_visible' => true,
            ]);

            return $this->response($conversation, $assistantMessage, $userMessage);
        } catch (Throwable $e) {
            Log::warning('Employee AI assistant chat failed', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
                'language' => $language,
            ]);

            $errorText = $this->failureMessage($e, $language);
            $assistantMessage = $conversation->messages()->create([
                'role' => AiMessageRole::Assistant,
                'content' => $errorText,
                'intent' => 'employee_assistant_error',
                'tool_results' => ['error' => mb_substr($e->getMessage(), 0, 500), 'language' => $language],
                'is_visible' => true,
            ]);

            // Preserve the saved user message and return the assistant error message.
            return $this->response($conversation, $assistantMessage, $userMessage, false);
        }
    }

    private function resolveConversation(User $user, ?int $conversationId, string $message): AiChatConversation
    {
        if ($conversationId) {
            $conversation = AiChatConversation::query()->find($conversationId);

            if ($conversation === null) {
                throw new AuthorizationException('The selected conversation could not be found.');
            }

            if ((int) $conversation->user_id !== (int) $user->id) {
                throw new AuthorizationException('You do not have access to this conversation.');
            }

            if ($conversation->is_archived) {
                $conversation->forceFill(['is_archived' => false])->save();
            }

            $this->applyFirstMessageTitle($conversation, $message);

            return $conversation;
        }

        return AiChatConversation::create([
            'user_id' => $user->id,
            'title' => $this->titleFromMessage($message),
            'is_archived' => false,
        ]);
    }

    /**
     * Replace a placeholder title ("New Chat", blank, …) with a title derived
     * from the employee's first message. Conversations that already hold
     * messages, or that carry a user-chosen title, are left untouched so a
     * manual rename is never lost.
     */
    private function applyFirstMessageTitle(AiChatConversation $conversation, string $message): void
    {
        $title = trim((string) $conversation->title);

        if (! in_array(mb_strtolower($title), self::PLACEHOLDER_TITLES, true)) {
            return;
        }

        if ($conversation->messages()->exists()) {
            return;
        }

        $conversation->forceFill(['title' => $this->titleFromMessage($message)])->save();
    }

    private function titleFromMessage(string $message): string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', $message));

        return $normalized !== '' ? Str::limit($normalized, 60) : 'New Chat';
    }

    private function generateReply(User $user, AiChatConversation $conversation, string $message, string $language): string
    {
        $history = $this->historyTranscript($conversation);
        $system = $this->systemPrompt($user, $language, $message);
        $userPrompt = $this->userPrompt($history, $message, $language);

        $request = new AiRequest(
            model: (string) (config('ai_reports.ai.model') ?? config('ai.model', 'gpt-4o-mini')),
            maxTokens: 1400,
            temperature: 0.4,
            expectJson: false,
            metadata: ['analysis_type' => 'employee_chat', 'scope' => 'employee_assistant', 'language' => $language],
        );

        $completion = $this->provider->complete($system, $userPrompt, $request);
        $text = trim($completion->text);

        if ($text === '') {
            throw new AiProviderException('The AI assistant returned an empty response.');
        }

        $normalized = $this->normalizeAssistantText($text, $message, $history, $language);

        if ($normalized === '') {
            throw new AiProviderException('The AI assistant response could not be processed.');
        }

        return $normalized;
    }

    private function systemPrompt(User $user, string $language, string $message): string
    {
        $basePrompt = ($this->systemPrompt ?? new AiChatSystemPrompt())->systemPrompt($user);
        $name = trim((string) ($user->name ?? ''));
        $languageRule = $this->languageInstruction($language, $message);

        return implode("\n", [
            $basePrompt,
            '',
            'User context:',
            $name !== '' ? "You are assisting {$name}." : 'You are assisting an authenticated employee.',
            '',
            'Language instructions:',
            $languageRule,
            '- If the user explicitly asks you to switch language, follow that request for the response.',
            '- Preserve the meaning of any information the employee provides; do not change facts when translating or rephrasing.',
        ]);
    }

    private function userPrompt(string $history, string $message, string $language): string
    {
        $parts = [];

        if ($history !== '') {
            $parts[] = 'Conversation history (oldest to newest):';
            $parts[] = $history;
            $parts[] = '';
        }

        $parts[] = 'Employee message:';
        $parts[] = $message;
        $parts[] = '';
        $parts[] = 'Respond helpfully using only the employee-provided information. If the employee asked for a summary and details are missing, ask for the missing details and provide a short grounded draft only from what was provided.';
        $parts[] = 'Resolved language preference context: '.$language.'.';

        return implode("\n", $parts);
    }

    private function historyTranscript(AiChatConversation $conversation): string
    {
        $messages = AiChatMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('is_visible', true)
            ->whereIn('role', [AiMessageRole::User->value, AiMessageRole::Assistant->value, 'user', 'assistant'])
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get(['role', 'content'])
            ->reverse()
            ->values();

        $lines = [];
        $total = 0;

        foreach ($messages as $item) {
            $role = $item->role instanceof AiMessageRole ? $item->role->value : (string) $item->role;
            $label = $role === 'assistant' ? 'Assistant' : 'Employee';
            $content = trim(preg_replace('/\s+/', ' ', (string) $item->content) ?? (string) $item->content);
            $content = Str::limit($content, 800, '...');
            $line = $label.': '.$content;
            $total += strlen($line);

            if ($total > self::MAX_HISTORY_CHARS) {
                break;
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    private function normalizeAssistantText(string $text, string $message, string $history, string $language): string
    {
        // FakeAiProvider (dev default in this project) returns report-analysis JSON.
        // Convert it into a grounded, useful assistant message without fabricating
        // employee activities. If a real provider returns normal text, keep it.
        $decoded = json_decode($text, true);

        if (! is_array($decoded) || (! isset($decoded['overview']) && ! isset($decoded['issues']) && ! isset($decoded['requirement_match']))) {
            return $text;
        }

        $cleanMessage = trim(preg_replace('/\s+/', ' ', $message) ?? $message);
        $excerpt = Str::limit($cleanMessage, 220, '...');
        $effective = $this->effectiveLanguageForFallback($language, $message);

        $wantsSummary = (bool) preg_match('/summary|accomplish|report|draft|weekly|daily|monthly|buwanang|lingguhang|araw-?araw|ulat|buod|isulat|ayusin|gawing propesyonal|grammar/i', $cleanMessage);

        $lines = [];
        if ($wantsSummary) {
            if ($effective === 'filipino') {
                $lines[] = '## Draft ng Accomplishment / Ulat (Batay Lamang sa Iyong Mensahe)';
                $lines[] = '';
                $lines[] = '**Ibinahagi mo:** '.$excerpt;
                $lines[] = '';
                $lines[] = '**Mga natapos na gawain / accomplishments**';
                $lines[] = '- '.($cleanMessage !== '' ? $excerpt : 'Wala pang ibinigay. Pakisabi ang mga gawaing natapos mo.');
                $lines[] = '';
                $lines[] = '**Mga hamon na naranasan**';
                $lines[] = '- Wala pang ibinigay. Ibahagi ang mga hadlang, isyu, o pagkaantala kung mayroon.';
                $lines[] = '';
                $lines[] = '**Mga susunod na plano**';
                $lines[] = '- Wala pang ibinigay. Ibahagi ang mga susunod mong gagawin.';
                $lines[] = '';
                $lines[] = 'Kung ipapadala mo ang iyong mga natapos na gawain, accomplishments, hamon, at susunod na plano, maaari ko itong ayusin bilang malinaw na daily, weekly, o monthly summary na maaari mong i-save at gamitin sa report.';
            } elseif ($effective === 'taglish') {
                $lines[] = '## Accomplishment / Report Draft (Batay sa Message Mo)';
                $lines[] = '';
                $lines[] = '**Na-share mo:** '.$excerpt;
                $lines[] = '';
                $lines[] = '**Completed tasks / accomplishments**';
                $lines[] = '- '.($cleanMessage !== '' ? $excerpt : 'Wala pang details. Pakishare kung anong tasks ang natapos mo.');
                $lines[] = '';
                $lines[] = '**Challenges encountered**';
                $lines[] = '- Wala pang details. I-share kung may blockers, issues, or delays.';
                $lines[] = '';
                $lines[] = '**Next plans**';
                $lines[] = '- Wala pang details. I-share kung ano ang next steps mo.';
                $lines[] = '';
                $lines[] = 'Kapag na-share mo ang completed tasks, accomplishments, challenges, at next plans mo, puwede ko itong i-format into a polished daily, weekly, or monthly summary na puwede mong i-save at gamitin sa report.';
            } else {
                $lines[] = '## Accomplishment / Report Draft (Grounded in Your Message)';
                $lines[] = '';
                $lines[] = '**What you shared:** '.$excerpt;
                $lines[] = '';
                $lines[] = '**Completed tasks / accomplishments**';
                $lines[] = '- '.($cleanMessage !== '' ? $excerpt : 'Not provided yet. Please tell me what tasks you completed.');
                $lines[] = '';
                $lines[] = '**Challenges encountered**';
                $lines[] = '- Not provided yet. Share any blockers, issues, or delays if applicable.';
                $lines[] = '';
                $lines[] = '**Next plans**';
                $lines[] = '- Not provided yet. Share what you plan to do next.';
                $lines[] = '';
                $lines[] = 'If you send your completed tasks, accomplishments, challenges, and next plans, I can format them into a polished daily, weekly, or monthly summary you can save and use in a report.';
            }
        } else {
            if ($effective === 'filipino') {
                $lines[] = 'Makakatulong ako batay lamang sa mga detalye na ibibigay mo.';
                $lines[] = '';
                $lines[] = '**Mensahe mo:** '.$excerpt;
                $lines[] = '';
                $lines[] = 'Pakibahagi ang mga tiyak na gawain, accomplishments, hamon, o tekstong gusto mong ayusin o pagandahin. Gagamitin ko lamang ang impormasyong ibibigay mo at hindi ako magdadagdag ng kathang-isip na mga gawain.';
            } elseif ($effective === 'taglish') {
                $lines[] = 'Makakatulong ako gamit lang ang details na ibibigay mo.';
                $lines[] = '';
                $lines[] = '**Message mo:** '.$excerpt;
                $lines[] = '';
                $lines[] = 'Paki-share ang specific tasks, accomplishments, challenges, or report text na gusto mong i-organize or i-improve. Gagamitin ko lang ang information na ibibigay mo at hindi ako mag-a-add ng fabricated activities.';
            } else {
                $lines[] = 'I can help with that using the details you provide.';
                $lines[] = '';
                $lines[] = '**Your message:** '.$excerpt;
                $lines[] = '';
                $lines[] = 'Please share the specific tasks, accomplishments, challenges, or report text you want me to organize or improve. I will only use information you provide and will not add fabricated activities.';
            }
        }

        if ($history === '') {
            $lines[] = '';
            if ($effective === 'filipino') {
                $lines[] = '_Tip: Maaari mong ipagpatuloy ang pag-uusap na ito at pananatilihin ko ang konteksto._';
            } elseif ($effective === 'taglish') {
                $lines[] = '_Tip: Puwede mong i-continue ang conversation na ito at ike-keep ko ang context._';
            } else {
                $lines[] = '_Tip: You can continue this conversation and I’ll keep the context._';
            }
        }

        return implode("\n", $lines);
    }

    private function failureMessage(Throwable $e, string $language): string
    {
        $effective = $language === 'auto' ? 'english' : $language;
        $effective = $effective === 'tagalog' ? 'filipino' : $effective;

        if ($e instanceof AiProviderConfigurationException) {
            if ($effective === 'filipino') {
                return 'Hindi available ngayon ang JPC AIDE dahil hindi pa naka-configure ang AI provider. Nai-save na ang iyong mensahe. Subukang muli kapag na-configure na ang AI service, o magpatuloy sa pag-save ng draft sa Create Report form.';
            }
            if ($effective === 'taglish') {
                return 'Hindi available ngayon ang JPC AIDE dahil hindi pa configured ang AI provider. Na-save na ang message mo. Subukan ulit kapag configured na ang AI service, o puwede kang mag-save ng draft details sa Create Report form.';
            }

            return 'JPC AIDE is not available right now because the AI provider is not configured. Your message has been saved. Please try again after the AI service is configured, or continue by saving your draft details in the Create Report form.';
        }

        if ($e instanceof AiProviderException) {
            if ($effective === 'filipino') {
                return 'Hindi makapagbigay ng tugon ang JPC AIDE ngayon. Nai-save na ang iyong mensahe. Pakisubukang muli maya-maya.';
            }
            if ($effective === 'taglish') {
                return 'Hindi makapag-generate ng response ang JPC AIDE ngayon. Na-save na ang message mo. Subukan ulit mamaya.';
            }

            return 'JPC AIDE could not generate a response right now. Your message has been saved. Please try again in a moment.';
        }

        if ($effective === 'filipino') {
            return 'Nagkaroon ng hindi inaasahang error ang JPC AIDE. Nai-save na ang iyong mensahe. Pakisubukang muli.';
        }
        if ($effective === 'taglish') {
            return 'Nagka-unexpected error ang JPC AIDE. Na-save na ang message mo. Subukan ulit.';
        }

        return 'JPC AIDE encountered an unexpected error. Your message has been saved. Please try again.';
    }

    /**
     * @return array<string, mixed>
     */
    private function response(AiChatConversation $conversation, AiChatMessage $assistantMessage, AiChatMessage $userMessage, bool $ok = true): array
    {
        return [
            'ok' => $ok,
            'conversation_id' => $conversation->id,
            'message' => $assistantMessage,
            'user_message' => $userMessage,
        ];
    }

    private function resolveLanguage(User $user, string $message, ?string $override = null): string
    {
        $candidate = $this->normalizeLanguage($override);
        if ($candidate !== 'auto') {
            return $candidate;
        }

        $userPref = $this->normalizeLanguage($user->ai_language_preference ?? null);
        if ($userPref !== 'auto') {
            return $userPref;
        }

        return $this->detectLanguage($message);
    }

    private function normalizeLanguage(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        if ($value === 'tagalog') {
            $value = 'filipino';
        }

        return in_array($value, self::ALLOWED_LANGUAGES, true) ? $value : 'auto';
    }

    private function detectLanguage(string $message): string
    {
        $text = mb_strtolower($message);
        $filipino = 0;
        $english = 0;

        $filipinoMarkers = [
            ' ako ', ' ikaw ', ' siya ', ' kami ', ' tayo ', ' sila ', ' ko ', ' mo ', ' ka ', ' namin ', ' natin ', ' nila ',
            ' ang ', ' ng ', ' mga ', ' sa ', ' nasa ', ' kay ', ' para ', ' para sa ', ' at ', ' o ', ' pero ', ' kasi ',
            ' po ', ' opo ', ' hindi ', ' oo ', ' wala ', ' meron ', ' may ', ' gawin ', ' ginawa ', ' gagawin ', ' tapos ',
            ' natapos ', ' tulong ', ' tulungan ', ' paki', ' pwede ', ' puwede ', ' kailangan ', ' gusto ', ' salamat ',
            ' kamusta ', ' araw ', ' linggo ', ' buwan ', ' ulat ', ' buod ', ' hamon ', ' plano ', ' susunod ',
        ];

        $padded = ' '.preg_replace('/\s+/u', ' ', $text).' ';
        foreach ($filipinoMarkers as $marker) {
            if (str_contains($padded, $marker)) {
                $filipino++;
            }
        }

        $englishMarkers = [
            ' the ', ' and ', ' or ', ' but ', ' with ', ' for ', ' this ', ' that ', ' my ', ' your ', ' we ', ' please ',
            ' completed ', ' tasks ', ' report ', ' summary ', ' weekly ', ' monthly ', ' daily ', ' challenges ', ' next ',
            ' plan ', ' help ', ' create ', ' write ', ' improve ',
        ];
        foreach ($englishMarkers as $marker) {
            if (str_contains($padded, $marker)) {
                $english++;
            }
        }

        if ($filipino >= 2 && $english >= 2) {
            return 'taglish';
        }
        if ($filipino > $english && $filipino >= 1) {
            return 'filipino';
        }

        return 'english';
    }

    private function effectiveLanguageForFallback(string $language, string $message): string
    {
        $language = $this->normalizeLanguage($language);
        if ($language === 'auto') {
            $language = $this->detectLanguage($message);
        }

        return $language === 'tagalog' ? 'filipino' : $language;
    }

    private function languageInstruction(string $language, string $message): string
    {
        $language = $this->normalizeLanguage($language);

        return match ($language) {
            'english' => '- Respond in clear, professional English unless the user explicitly asks for another language.',
            'filipino' => '- Tumugon sa malinaw at propesyonal na Filipino/Tagalog maliban kung tahasang humiling ang user ng ibang wika. Panatilihin ang kahulugan ng mga impormasyong ibinigay ng user.',
            'taglish' => '- Respond in natural, professional Taglish (a clear mix of Filipino and English) unless the user explicitly asks for another language. Preserve the meaning of the information provided by the user.',
            default => '- Automatically follow the language used in the user\'s message (English, Filipino/Tagalog, Taglish, or another supported language when possible). If the user writes in Filipino/Tagalog, respond in Filipino/Tagalog; if Taglish, respond in Taglish; if English, respond in English. Preserve the meaning of the information provided.',
        };
    }
}
