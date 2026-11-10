<?php

namespace Tests\Unit\Services\AI;

use App\Models\User;
use App\Services\AI\AiToolRegistry;
use App\Services\AI\Tools\AiToolInterface;
use Tests\TestCase;

class AiToolRegistryTest extends TestCase
{
    public function test_register_and_find_return_registered_tool(): void
    {
        $registry = new AiToolRegistry;
        $tool = $this->fakeTool('GetFakeTool');

        $registry->register($tool);

        $this->assertSame($tool, $registry->find('GetFakeTool'));
    }

    public function test_find_unknown_tool_returns_null(): void
    {
        $registry = new AiToolRegistry;

        $this->assertNull($registry->find('MissingTool'));
    }

    public function test_all_returns_registered_tools_keyed_by_name(): void
    {
        $registry = new AiToolRegistry;
        $a = $this->fakeTool('ToolA');
        $b = $this->fakeTool('ToolB');

        $registry->register($a);
        $registry->register($b);

        $all = $registry->all();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('ToolA', $all);
        $this->assertArrayHasKey('ToolB', $all);
        $this->assertSame($a, $all['ToolA']);
    }

    public function test_empty_registry_returns_no_tools(): void
    {
        $registry = new AiToolRegistry;

        $this->assertSame([], $registry->all());
    }

    private function fakeTool(string $name): AiToolInterface
    {
        return new class($name) implements AiToolInterface
        {
            public function __construct(private readonly string $toolName) {}

            public function name(): string
            {
                return $this->toolName;
            }

            public function description(): string
            {
                return 'Fake tool for registry tests';
            }

            public function parameters(): array
            {
                return [];
            }

            public function requiredPermission(): ?string
            {
                return null;
            }

            public function execute(array $params, User $user): array
            {
                return [];
            }
        };
    }
}
