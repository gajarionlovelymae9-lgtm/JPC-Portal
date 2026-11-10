import { apiRequest } from '../../services/api'

export type AiLanguagePreference = 'auto' | 'english' | 'filipino' | 'tagalog' | 'taglish'

export interface AiLanguagePreferenceOption {
  value: AiLanguagePreference
  label: string
}

export interface AiLanguagePreferencePayload {
  ai_language_preference: AiLanguagePreference
  options?: AiLanguagePreferenceOption[]
}

export async function getAiLanguagePreference(): Promise<AiLanguagePreferencePayload> {
  const response = await apiRequest<AiLanguagePreferencePayload>('/ai/language-preference', { method: 'GET' })
  return (response.data as AiLanguagePreferencePayload) ?? { ai_language_preference: 'auto' }
}

export async function updateAiLanguagePreference(
  preference: AiLanguagePreference,
): Promise<AiLanguagePreferencePayload> {
  const response = await apiRequest<AiLanguagePreferencePayload>('/ai/language-preference', {
    method: 'PUT',
    body: { ai_language_preference: preference },
  })
  return (response.data as AiLanguagePreferencePayload) ?? { ai_language_preference: preference }
}

export const AI_LANGUAGE_OPTIONS: AiLanguagePreferenceOption[] = [
  { value: 'auto', label: 'Auto Detect' },
  { value: 'english', label: 'English' },
  { value: 'filipino', label: 'Filipino / Tagalog' },
  { value: 'taglish', label: 'Taglish' },
]
