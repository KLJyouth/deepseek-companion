<?php
namespace Controllers;

class DocPreviewController {
    private $wsServer;
    private $markdownParser;
    
    public function __construct() {
        $this->wsServer = new \Libs\WebSocketServer();
        $this->markdownParser = new \Libs\MarkdownParser();
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
