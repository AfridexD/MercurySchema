<?php
/**
 * A single schema instance attached to a post.
 *
 * Pure PHP: no WordPress calls, so it can be unit tested in isolation.
 *
 * @package UnlimitedSchema
 */

namespace UnlimitedSchema\Core;

class Schema
{
    private string $id;
    private string $type;
    private bool $enabled = true;
    private array $data = [];
    private array $conditions = [];

    public function __construct(string $type, ?string $id = null)
    {
        $this->type = $type;
        $this->id = $id ?? self::generateId($type);
    }

    public static function generateId(string $type): string
    {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $type));
        return trim($slug, '-') . '-' . bin2hex(random_bytes(4));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setConditions(array $conditions): self
    {
        $this->conditions = $conditions;
        return $this;
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function setEnabled($enabled): self
    {
        $this->enabled = (bool) $enabled;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'enabled'    => $this->enabled,
            'data'       => $this->data,
            'conditions' => $this->conditions,
        ];
    }

    /**
     * @throws \InvalidArgumentException When the array has no usable type.
     */
    public static function fromArray(array $arr): self
    {
        if (empty($arr['type']) || !is_string($arr['type'])) {
            throw new \InvalidArgumentException('Schema type is required.');
        }
        $id = isset($arr['id']) && is_string($arr['id']) && $arr['id'] !== '' ? $arr['id'] : null;

        $schema = new self($arr['type'], $id);
        $schema->enabled = isset($arr['enabled']) ? (bool) $arr['enabled'] : true;
        $schema->data = is_array($arr['data'] ?? null) ? $arr['data'] : [];
        $schema->conditions = is_array($arr['conditions'] ?? null) ? $arr['conditions'] : [];
        return $schema;
    }
}
