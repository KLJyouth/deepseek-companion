<?php
namespace App\Controllers;

use App\Libs\WebSocketServer;
use App\Libs\MarkdownParser;

class DocPreviewController {
    private $wsServer;
    private $markdownParser;
    
    public function __construct() {
        $this->wsServer = new WebSocketServer();
        $this->markdownParser = new MarkdownParser();
    }
    
    public function handlePreview(string $docId): void {
        $this->wsServer->on('doc.change', function($client, $data) {
            $html = $this->markdownParser->toHtml($data['content']);
            $this->wsServer->broadcast('doc.preview', [
                'doc_id' => $data['doc_id'],
                'html' => $html
            ]);
        });
    }
}