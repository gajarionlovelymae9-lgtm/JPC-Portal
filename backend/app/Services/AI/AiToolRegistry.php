<?php
namespace App\Services\AI;
use App\Services\AI\Tools\AiToolInterface;

class AiToolRegistry {
    private array $tools = [];
    
    public function register(AiToolInterface $tool) {
        $this->tools[$tool->name()] = $tool;
    }
    
    public function find(string $name): ?AiToolInterface {
        return $this->tools[$name] ?? null;
    }
    
    public function all(): array {
        return $this->tools;
    }
}