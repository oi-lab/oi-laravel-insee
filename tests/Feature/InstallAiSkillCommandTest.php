<?php

beforeEach(function () {
    $this->base = sys_get_temp_dir().'/oi-insee-cmd-'.uniqid();
    mkdir($this->base, 0755, true);
    app()->setBasePath($this->base);
});

afterEach(function () {
    if (! is_dir($this->base)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($this->base);
});

it('installs the skill files for Claude and Junie', function () {
    $this->artisan('oi-insee:install-ai-skill')->assertSuccessful();

    expect(file_exists($this->base.'/.claude/skills/oilab-laravel-insee/SKILL.md'))->toBeTrue()
        ->and(file_exists($this->base.'/.junie/skills/oilab-laravel-insee/SKILL.md'))->toBeTrue()
        ->and(file_get_contents($this->base.'/.claude/skills/oilab-laravel-insee/SKILL.md'))
        ->toContain('OI Laravel INSEE');
});

it('creates a CLAUDE.md with the rules section when none exists', function () {
    $this->artisan('oi-insee:install-ai-skill')->assertSuccessful();

    $claude = file_get_contents($this->base.'/CLAUDE.md');

    expect($claude)->toContain('=== oi-lab/oi-laravel-insee rules ===')
        ->and($claude)->toContain('Activate `oilab-laravel-insee`');
});

it('appends the rules section to an existing CLAUDE.md without losing content', function () {
    file_put_contents($this->base.'/CLAUDE.md', "# Existing project rules\n");

    $this->artisan('oi-insee:install-ai-skill')->assertSuccessful();

    $claude = file_get_contents($this->base.'/CLAUDE.md');

    expect($claude)->toContain('# Existing project rules')
        ->and($claude)->toContain('=== oi-lab/oi-laravel-insee rules ===');
});

it('does not duplicate the rules section when run twice', function () {
    $this->artisan('oi-insee:install-ai-skill')->assertSuccessful();
    $this->artisan('oi-insee:install-ai-skill')->assertSuccessful();

    $claude = file_get_contents($this->base.'/CLAUDE.md');

    expect(substr_count($claude, '=== oi-lab/oi-laravel-insee rules ==='))->toBe(1);
});
