<?php

namespace Tests\Feature;

use App\Http\Controllers\API\Links\LinkController;
use Illuminate\Http\Request;
use Tests\TestCase;

class LinkCommentSanitizationTest extends TestCase
{
    /**
     * @dataProvider javascriptInjectionPayloads
     */
    public function test_sanitize_comment_removes_javascript_injections($comment, $expected)
    {
        $request = new Request(['comment' => $comment]);
        $controller = new LinkController();
        $method = new \ReflectionMethod(LinkController::class, 'sanitizeComment');
        // make function sanitizeComment public before running this test

        $method->invoke($controller, $request);

        $this->assertSame($expected, $request->input('comment'));
    }

    public static function javascriptInjectionPayloads()
    {
        return [
            'script element' => ['<script>alert(1)</script>Safe', '<p>Safe</p>'],
            'image error handler' => ['<img src=x onerror=alert(1)>Safe', '<p><img src="x" alt="x">Safe</p>'],
            'svg load handler' => ['<svg onload=alert(1)>Safe</svg>', '<p>Safe</p>'],
            'javascript link' => ['<a href="javascript:alert(1)">Click</a>', '<p><a>Click</a></p>'],
            'iframe javascript source' => ['<iframe src="javascript:alert(1)">Safe</iframe>', '<p>Safe</p>'],
            'javascript in style attribute' => [
                '<div style="background-image:url(javascript:alert(1))">Safe</div>',
                '<div>Safe</div>',
            ],
        ];
    }
}
