<?php

namespace Tests\Unit;

use Codeception\Test\Unit;

class TemplateTest extends Unit
{
    private function template(): \View\Template
    {
        $config = new \Config\Config();
        $config->app_path = dirname(__DIR__, 2);  // project root: real templates/
        return new \View\Template($config);
    }

    public function testRendersTemplateWithVars()
    {
        $page = $this->template();
        $page->setTemplate("index.tpl.php");
        $page->set('username', 'Rob');
        $page->set('site_title', 'Test Site');

        $html = $page->grabTheGoods();

        $this->assertStringContainsString('Welcome back, Rob!', $html);
        $this->assertStringContainsString('<h1>Test Site</h1>', $html);
    }

    public function testEscapesHtmlInSiteTitle()
    {
        $page = $this->template();
        $page->setTemplate("index.tpl.php");
        $page->set('username', 'Rob');
        $page->set('site_title', '<script>alert(1)</script>');

        $html = $page->grabTheGoods();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testConditionalVarShownOnlyWhenSet()
    {
        $page = $this->template();
        $page->setTemplate("index.tpl.php");
        $page->set('username', 'Rob');
        $page->set('site_title', 'Test Site');
        $this->assertStringNotContainsString('Version:', $page->grabTheGoods());

        $page = $this->template();
        $page->setTemplate("index.tpl.php");
        $page->set('username', 'Rob');
        $page->set('site_title', 'Test Site');
        $page->set('site_version', '1.2.3');
        $this->assertStringContainsString('Version: 1.2.3', $page->grabTheGoods());
    }

    public function testLayoutNestingViaGrabTheGoods()
    {
        // The wwwroot pattern: render an inner template, hand it to the layout.
        $inner = $this->template();
        $inner->setTemplate("index.tpl.php");
        $inner->set('username', 'Rob');
        $inner->set('site_title', 'Test Site');

        $layout = $this->template();
        $layout->setTemplate("layout/base.tpl.php");
        $layout->set('page_title', 'Nested Page');
        $layout->set('page_content', $inner->grabTheGoods());

        $html = $layout->grabTheGoods();

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>Nested Page</title>', $html);
        $this->assertStringContainsString('Welcome back, Rob!', $html);
    }

    public function testLayoutPageTitleDefaultsWhenUnset()
    {
        $layout = $this->template();
        $layout->setTemplate("layout/base.tpl.php");
        $layout->set('page_content', 'hello');

        $this->assertStringContainsString('<title>Site</title>', $layout->grabTheGoods());
    }
}
