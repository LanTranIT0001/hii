<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Message;
use App\Models\Pin;
use App\Models\User;
use Core\Controller;
use Core\Database;
use PDOException;

class MessageController extends Controller
{
    public function inboxAction(): void
    {
        $userId = $this->requireAuth();
        $db = Database::connection($this->config['db']);
        $messageModel = new Message($db);

        try {
            $conversations = $messageModel->listUserConversations($userId);
        } catch (PDOException $e) {
            $conversations = [];
        }

        $selectedConversationId = isset($_GET['conversation_id']) ? (int) $_GET['conversation_id'] : 0;
        $activeConversationId = $selectedConversationId;
        if ($activeConversationId <= 0 && !empty($conversations)) {
            $activeConversationId = (int) $conversations[0]['id'];
        }

        if ($activeConversationId > 0) {
            try {
                $messageModel->markConversationRead($activeConversationId, $userId);
                $conversations = $messageModel->listUserConversations($userId);
            } catch (PDOException $e) {
                // keep the original conversation list if marking read fails
            }
        }

        $messages = [];
        if ($activeConversationId > 0) {
            try {
                $messages = $messageModel->listMessages($activeConversationId);
            } catch (PDOException $e) {
                $messages = [];
            }
        }

        $this->view('messages/inbox', [
            'currentUserId' => $userId,
            'conversations' => $conversations,
            'activeConversationId' => $activeConversationId,
            'messages' => $messages,
        ]);
    }

    public function startAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('message/inbox');
        }
        $userId = $this->requireAuth();
        $db = Database::connection($this->config['db']);
        $peerIdentifier = isset($_POST['peer_username']) ? trim((string) $_POST['peer_username']) : '';
        $peerId = 0;

        if ($peerIdentifier !== '') {
            $userModel = new User($db);
            $peerUser = $userModel->findByIdentifier($peerIdentifier);
            $peerId = $peerUser ? (int) $peerUser['id'] : 0;
        }

        if ($peerId <= 0 || $peerId === $userId) {
            $this->redirect('message/inbox');
        }

        $messageModel = new Message($db);
        try {
            $conversationId = $messageModel->findOrCreateConversation($userId, $peerId);
        } catch (PDOException $e) {
            $this->redirect('message/inbox');
            return;
        }
        $this->redirect('message/inbox', ['conversation_id' => $conversationId]);
    }

    public function deleteAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('message/inbox');
        }

        $userId = $this->requireAuth();
        $conversationId = isset($_POST['conversation_id']) ? (int) $_POST['conversation_id'] : 0;

        if ($conversationId <= 0) {
            $this->redirect('message/inbox');
        }

        $db = Database::connection($this->config['db']);
        $messageModel = new Message($db);
        try {
            $messageModel->deleteConversation($conversationId, $userId);
        } catch (PDOException $e) {
            // ignore delete error and continue redirect
        }

        $this->redirect('message/inbox');
    }

    public function sendAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('message/inbox');
        }
        $userId = $this->requireAuth();
        $conversationId = isset($_POST['conversation_id']) ? (int) $_POST['conversation_id'] : 0;
        $content = isset($_POST['content']) ? trim((string) $_POST['content']) : '';
        $sharedPinId = isset($_POST['shared_pin_id']) ? (int) $_POST['shared_pin_id'] : 0;
        if ($sharedPinId <= 0 && $content !== '') {
            $sharedPinId = $this->extractSharedPinIdFromContent($content);
        }
        if ($conversationId <= 0 || ($content === '' && $sharedPinId <= 0)) {
            $this->redirect('message/inbox');
        }

        $db = Database::connection($this->config['db']);
        $messageModel = new Message($db);
        try {
            $messageModel->send(
                $conversationId,
                $userId,
                $content === '' ? 'Da chia se 1 pin voi ban.' : $content,
                $sharedPinId > 0 ? $sharedPinId : null
            );
        } catch (PDOException $e) {
            // Keep redirecting even if schema is not fully migrated.
        }
        $this->redirect('message/inbox', ['conversation_id' => $conversationId]);
    }

    private function extractSharedPinIdFromContent(string $content): int
    {
        $content = trim($content);
        if ($content === '') {
            return 0;
        }

        $candidate = $content;
        if (preg_match('~https?://\S+~i', $content, $match)) {
            $candidate = $match[0];
        } elseif (preg_match('~index\.php\?r=pin/detail&id=\d+~i', $content, $match)) {
            $candidate = $match[0];
        }

        $query = parse_url($candidate, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return 0;
        }

        parse_str($query, $params);
        if (($params['r'] ?? '') !== 'pin/detail') {
            return 0;
        }
        $pinId = isset($params['id']) ? (int) $params['id'] : 0;
        if ($pinId <= 0) {
            return 0;
        }

        $db = Database::connection($this->config['db']);
        $pinModel = new Pin($db);
        $pin = $pinModel->findById($pinId);

        return $pin ? $pinId : 0;
    }
}
