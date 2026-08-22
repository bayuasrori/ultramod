<?php

namespace App\Platform\Manifests;

use App\Platform\Exceptions\InvalidManifestException;

class AppManifest
{
    /**
     * @param  array<string>  $permissions
     * @param  array<string, string> $requires
     * @param  array<string> $extends
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $name,
        public readonly string $version,
        public readonly string $description,
        public readonly string $provider,
        public readonly string $platformConstraint,
        public readonly array $permissions = [],
        public readonly array $requires = [],
        public readonly array $extends = [],
    ) {
    }

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new InvalidManifestException("Manifest file [{$path}] does not exist.");
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            throw new InvalidManifestException("Manifest file [{$path}] is not valid JSON.");
        }

        return self::fromArray($data);
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['id', 'name', 'version', 'provider'] as $field) {
            if (empty($data[$field]) || ! is_string($data[$field])) {
                throw new InvalidManifestException("Manifest field [{$field}] is required.");
            }
        }

        $constraint = $data['requires']['platform'] ?? '*';
        
        $requires = $data['requires'] ?? [];
        if (isset($requires['platform'])) {
            unset($requires['platform']);
        }

        return new self(
            id: $data['id'],
            type: $data['type'] ?? 'application',
            name: $data['name'],
            version: $data['version'],
            description: $data['description'] ?? '',
            provider: $data['provider'],
            platformConstraint: $constraint,
            permissions: array_values(array_filter(
                $data['permissions'] ?? [],
                fn ($p) => is_string($p) && $p !== '',
            )),
            requires: $requires,
            extends: $data['extends'] ?? [],
        );
    }

    public function directory(): string
    {
        return config('platform.apps_path').DIRECTORY_SEPARATOR.$this->id;
    }

    /**
     * Minimal semver support for "*" and "^major.minor" constraints.
     */
    public function supportsPlatform(string $platformVersion): bool
    {
        if ($this->platformConstraint === '*') {
            return true;
        }

        if (preg_match('/^\^(\d+)\.(\d+)(?:\.(\d+))?$/', $this->platformConstraint, $m)) {
            $parts = array_map('intval', explode('.', $platformVersion));
            $major = $parts[0] ?? 0;
            $minor = $parts[1] ?? 0;

            return $major === (int) $m[1] && $minor >= (int) $m[2];
        }

        return $this->platformConstraint === $platformVersion;
    }
}
