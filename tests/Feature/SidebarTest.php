<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): static
    {
        return $this->loginPortal(['id' => '2', 'name' => 'Budi', 'role' => $role, 'level' => 10]);
    }

    private function sidebar(string $html): string
    {
        preg_match('/<aside class="sidebar-desktop".*?<\/aside>/s', $html, $m);

        return $m[0] ?? '';
    }

    public function test_sidebar_follows_the_hris_pattern(): void
    {
        $html = $this->sidebar($this->as('admin')->get('/p2h')->getContent());

        $this->assertStringNotContainsString('sidebar-title', $html, 'logo saja, seperti HRIS');
        $this->assertStringNotContainsString('Menu Utama', $html);
        $this->assertStringNotContainsString('Sistem &amp; Ekosistem', $html);
        $this->assertStringNotContainsString('text-info', $html);
        foreach (['Dashboard', 'Isi P2H', 'Riwayat P2H', 'Unit', 'Checklist', 'Data Master'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function test_the_group_with_the_active_page_is_open_and_the_others_are_collapsed(): void
    {
        $home = $this->sidebar($this->as('admin')->get('/p2h')->getContent());
        $this->assertSame(2, substr_count($home, 'sidebar-group-toggle collapsed'));
        $this->assertSame(0, substr_count($home, 'has-active'));

        $history = $this->sidebar($this->as('admin')->get('/p2h/riwayat')->getContent());
        $this->assertSame(1, substr_count($history, 'has-active'));
        $this->assertSame(1, substr_count($history, 'sidebar-group-toggle collapsed'));

        $units = $this->sidebar($this->as('admin')->get('/p2h/unit')->getContent());
        $this->assertSame(1, substr_count($units, 'has-active'));
    }

    public function test_menu_items_depend_on_the_level(): void
    {
        $operator = $this->sidebar($this->as('operator')->get('/p2h')->getContent());
        $this->assertStringContainsString('Isi P2H', $operator);
        $this->assertStringContainsString('P2H Saya', $operator);
        $this->assertStringNotContainsString('Data Master', $operator);
        $this->assertStringNotContainsString('Unit', $operator);

        $viewer = $this->sidebar($this->as('viewer')->get('/p2h')->getContent());
        $this->assertStringNotContainsString('Isi P2H', $viewer);
        $this->assertStringContainsString('Riwayat P2H', $viewer);
        $this->assertStringContainsString('Data Master', $viewer);
    }
}
