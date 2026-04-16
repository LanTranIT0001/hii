<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Board;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Pin;
use App\Models\User;
use Core\Controller;
use Core\Database;
use PDOException;

class PinController extends Controller
{
    public function detailAction(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            $this->redirect('home/index');
        }

        $db = Database::connection($this->config['db']);
        $pinModel = new Pin($db);
        $pin = $pinModel->findById($id);

        if (!$pin) {
            $this->redirect('home/index');
        }

        $likeCount = $pinModel->getLikeCount($id);
        $isSaved = false;
        $isLiked = false;
        $boards = [];
        $comments = [];
        $conversations = [];
        $isFollowingAuthor = false;
        $authorFollowerCount = 0;
        $userId = $this->currentUserId();
        $authorId = (int) ($pin['user_id'] ?? 0);
        $pinUrl = 'index.php?r=pin/detail&id=' . $id;
        $shareLink = $pinUrl;

        $comments = $pinModel->listComments($id);
        if ($userId !== null) {
            $isSaved = $pinModel->isSavedByUser($id, $userId);
            $isLiked = $pinModel->hasLikedByUser($id, $userId);
            $boardModel = new Board($db);
            $boards = $boardModel->listByUser($userId);
            $messageModel = new Message($db);
            try {
                $conversations = $messageModel->listUserConversations($userId);
            } catch (PDOException $e) {
                $conversations = [];
            }
            $userModel = new User($db);
            $isFollowingAuthor = $userModel->isFollowing($userId, $authorId);
            $authorFollowerCount = $userModel->getFollowerCount($authorId);
        }

        $this->view('pins/detail', [
            'pin' => $pin,
            'isSaved' => $isSaved,
            'isLiked' => $isLiked,
            'comments' => $comments,
            'likeCount' => $likeCount,
            'boards' => $boards,
            'conversations' => $conversations,
            'isFollowingAuthor' => $isFollowingAuthor,
            'authorFollowerCount' => $authorFollowerCount,
            'pinUrl' => $pinUrl,
            'shareLink' => $shareLink,
        ]);
    }

    public function saveAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home/index');
        }
        $userId = $this->requireAuth();
        $pinId = isset($_POST['pin_id']) ? (int) $_POST['pin_id'] : 0;
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($pinId <= 0) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode(['success' => false]);
                exit;
            }
            $this->redirect('home/index');
        }

        $db = Database::connection($this->config['db']);
        $pinModel = new Pin($db);
        $saved = $pinModel->savePin($pinId, $userId);

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => $saved]);
            exit;
        }

        $this->redirect('pin/detail', ['id' => $pinId]);
    }

    public function unsaveAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home/index');
        }
        $userId = $this->requireAuth();
        $pinId = isset($_POST['pin_id']) ? (int) $_POST['pin_id'] : 0;
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($pinId <= 0) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode(['success' => false]);
                exit;
            }
            $this->redirect('home/index');
        }

        $db = Database::connection($this->config['db']);
        $pinModel = new Pin($db);
        $unsaved = $pinModel->unsavePin($pinId, $userId);
        if ($unsaved) {
            // Keep boards in sync with saved pins:
            // once a pin is unsaved, detach it from user's boards.
            $boardModel = new Board($db);
            $boardModel->removePinFromUserBoards($userId, $pinId);
        }

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => $unsaved]);
            exit;
        }

        $this->redirect('pin/detail', ['id' => $pinId]);
    }

    public function savedAction(): void
    {
        $this->requireAuth();
        $this->redirect('profile/show', ['tab' => 'saved-pins']);
    }

    public function likeAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home/index');
        }
        $userId = $this->requireAuth();
        $pinId = isset($_POST['pin_id']) ? (int) $_POST['pin_id'] : 0;
        if ($pinId <= 0) {
            $this->redirect('home/index');
        }

        $db = Database::connection($this->config['db']);
        $pinModel = new Pin($db);
        $wasLiked = $pinModel->hasLikedByUser($pinId, $userId);
        $pinModel->likePin($pinId, $userId);
        if (!$wasLiked) {
            $pin = $pinModel->findById($pinId);
            $ownerId = (int) ($pin['user_id'] ?? 0);
            if ($ownerId > 0 && $ownerId !== $userId) {
                $notificationModel = new Notification($db);
                $notificationModel->createLikeNotification($userId, $pinId, $ownerId);
            }
        }
        $this->redirect('pin/detail', ['id' => $pinId]);
    }

    public function unlikeAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home/index');
        }
        $userId = $this->requireAuth();
        $pinId = isset($_POST['pin_id']) ? (int) $_POST['pin_id'] : 0;
        if ($pinId <= 0) {
            $this->redirect('home/index');
        }

        $db = Database::connection($this->config['db']);
        $pinModel = new Pin($db);
        $pinModel->unlikePin($pinId, $userId);
        $this->redirect('pin/detail', ['id' => $pinId]);
    }

    public function commentAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home/index');
        }
        $userId = $this->requireAuth();
        $pinId = isset($_POST['pin_id']) ? (int) $_POST['pin_id'] : 0;
        $content = trim((string) ($_POST['content'] ?? ''));
        if ($pinId <= 0) {
            $this->redirect('home/index');
        }
        if ($content === '') {
            $this->redirect('pin/detail', ['id' => $pinId]);
        }

        $db = Database::connection($this->config['db']);
        $pinModel = new Pin($db);
        $added = $pinModel->addComment($pinId, $userId, $content);
        if ($added) {
            $pin = $pinModel->findById($pinId);
            $ownerId = (int) ($pin['user_id'] ?? 0);
            if ($ownerId > 0 && $ownerId !== $userId) {
                $notificationModel = new Notification($db);
                $notificationModel->createCommentNotification($userId, $pinId, $ownerId, $content);
            }
        }
        $this->redirect('pin/detail', ['id' => $pinId]);
    }

    public function reportAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home/index');
        }
        $userId = $this->requireAuth();
        $pinId = isset($_POST['pin_id']) ? (int) $_POST['pin_id'] : 0;
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($pinId <= 0) {
            $this->redirect('home/index');
        }

        $db = Database::connection($this->config['db']);
        $pinModel = new Pin($db);
        $reported = $pinModel->reportPin($pinId, $userId, $reason);

        $this->redirect('pin/detail', [
            'id' => $pinId,
            'report' => $reported ? 'success' : 'failed',
        ]);
    }

    public function followAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home/index');
        }
        $userId = $this->requireAuth();
        $authorId = isset($_POST['author_id']) ? (int) $_POST['author_id'] : 0;
        $pinId = isset($_POST['pin_id']) ? (int) $_POST['pin_id'] : 0;
        if ($pinId <= 0 || $authorId <= 0) {
            $this->redirect('home/index');
        }

        $db = Database::connection($this->config['db']);
        $userModel = new User($db);
        $isFollowing = $userModel->isFollowing($userId, $authorId);
        $userModel->follow($userId, $authorId);
        if (!$isFollowing) {
            $notificationModel = new Notification($db);
            $notificationModel->createFollowNotification($userId, $authorId);
        }
        $this->redirect('pin/detail', ['id' => $pinId]);
    }

    public function unfollowAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home/index');
        }
        $userId = $this->requireAuth();
        $authorId = isset($_POST['author_id']) ? (int) $_POST['author_id'] : 0;
        $pinId = isset($_POST['pin_id']) ? (int) $_POST['pin_id'] : 0;
        if ($pinId <= 0 || $authorId <= 0) {
            $this->redirect('home/index');
        }

        $db = Database::connection($this->config['db']);
        $userModel = new User($db);
        $userModel->unfollow($userId, $authorId);
        $this->redirect('pin/detail', ['id' => $pinId]);
    }

    public function shareChatAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home/index');
        }
        $userId = $this->requireAuth();
        $pinId = isset($_POST['pin_id']) ? (int) $_POST['pin_id'] : 0;
        $conversationId = isset($_POST['conversation_id']) ? (int) $_POST['conversation_id'] : 0;
        if ($pinId <= 0 || $conversationId <= 0) {
            $this->redirect('home/index');
        }

        $db = Database::connection($this->config['db']);
        $pinModel = new Pin($db);
        $pin = $pinModel->findById($pinId);
        if (!$pin) {
            $this->redirect('home/index');
        }
        $pinUrl = 'index.php?r=pin/detail&id=' . $pinId;

        $messageModel = new Message($db);
        if (!$messageModel->isUserInConversation($conversationId, $userId)) {
            $this->redirect('pin/detail', ['id' => $pinId]);
        }

        try {
            $messageModel->send(
                $conversationId,
                $userId,
                '',
                $pinId
            );
        } catch (PDOException $e) {
            // Ignore DB errors to keep UX stable on older schema.
        }

        $this->redirect('pin/detail', ['id' => $pinId, 'shared_chat' => 1]);
    }

    public function shareAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('home/index');
        }
        $userId = $this->requireAuth();
        $pinId = isset($_POST['pin_id']) ? (int) $_POST['pin_id'] : 0;
        $boardId = isset($_POST['board_id']) ? (int) $_POST['board_id'] : 0;
        if ($pinId <= 0 || $boardId <= 0) {
            $this->redirect('home/index');
        }

        $db = Database::connection($this->config['db']);
        $boardModel = new Board($db);
        $boards = $boardModel->listByUser($userId);
        $canShare = false;
        foreach ($boards as $board) {
            if ((int) $board['id'] === $boardId) {
                $canShare = true;
                break;
            }
        }
        if ($canShare) {
            $boardModel->addPin($boardId, $pinId);
        }

        $this->redirect('pin/detail', ['id' => $pinId, 'shared' => 1]);
    }
}
