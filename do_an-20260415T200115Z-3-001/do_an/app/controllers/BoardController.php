<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Board;
use Core\Controller;
use Core\Database;
use PDOException;

class BoardController extends Controller
{
    public function indexAction(): void
    {
        $db = Database::connection($this->config['db']);
        $boardModel = new Board($db);
        $viewerId = $this->currentUserId();
        try {
            $boards = $boardModel->listPublicBoardsWithPreviews($viewerId);
        } catch (PDOException $e) {
            $boards = [];
        }

        $this->view('boards/index', ['boards' => $boards]);
    }

    public function createAction(): void
    {
        $userId = $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
            $description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
            $privacy = isset($_POST['privacy']) ? strtoupper((string) $_POST['privacy']) : 'PUBLIC';
            if ($name !== '') {
                if ($privacy !== 'PRIVATE') {
                    $privacy = 'PUBLIC';
                }
                $db = Database::connection($this->config['db']);
                $boardModel = new Board($db);
                try {
                    $boardModel->create($userId, $name, $description, $privacy);
                } catch (PDOException $e) {
                    // Ignore to keep app running when schema is not migrated.
                }
                $this->redirect('board/index');
            }
        }

        $this->view('boards/create');
    }

    public function detailAction(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            $this->redirect('board/index');
        }
        $db = Database::connection($this->config['db']);
        $boardModel = new Board($db);
        try {
            $board = $boardModel->find($id);
        } catch (PDOException $e) {
            $board = null;
        }
        if (!$board) {
            $this->redirect('board/index');
        }
        try {
            $pins = $boardModel->pins($id);
        } catch (PDOException $e) {
            $pins = [];
        }

        $viewerId = $this->currentUserId();
        $isOwner = false;
        $savedPinsForAttach = [];
        if ($viewerId !== null) {
            try {
                $isOwner = $boardModel->isOwnedByUser($id, $viewerId);
                if ($isOwner) {
                    $savedPinsForAttach = $boardModel->listSavedPinsNotInBoard($viewerId, $id);
                }
            } catch (PDOException $e) {
                $isOwner = false;
                $savedPinsForAttach = [];
            }
        }

        $this->view('boards/detail', [
            'board' => $board,
            'pins' => $pins,
            'isOwner' => $isOwner,
            'savedPinsForAttach' => $savedPinsForAttach,
        ]);
    }

    public function saveAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('board/index');
        }

        $userId = $this->requireAuth();
        $boardId = isset($_POST['board_id']) ? (int) $_POST['board_id'] : 0;
        if ($boardId <= 0) {
            $this->redirect('board/index');
        }

        $db = Database::connection($this->config['db']);
        $boardModel = new Board($db);
        $board = $boardModel->find($boardId);
        if (!$board || strtoupper((string) ($board['privacy'] ?? 'PUBLIC')) !== 'PUBLIC') {
            $this->redirect('board/index');
        }

        if ((int) ($board['user_id'] ?? 0) === $userId) {
            $this->redirect('board/index');
        }

        $boardModel->saveBoard($userId, $boardId);
        $this->redirect('board/index', ['saved' => 1]);
    }

    public function unsaveAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('board/index');
        }

        $userId = $this->requireAuth();
        $boardId = isset($_POST['board_id']) ? (int) $_POST['board_id'] : 0;
        if ($boardId <= 0) {
            $this->redirect('board/index');
        }

        $db = Database::connection($this->config['db']);
        $boardModel = new Board($db);
        $boardModel->unsaveBoard($userId, $boardId);
        $this->redirect('board/index', ['saved' => 0]);
    }

    public function attachSavedPinAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('board/index');
        }

        $userId = $this->requireAuth();
        $boardId = isset($_POST['board_id']) ? (int) $_POST['board_id'] : 0;
        $pinId = isset($_POST['pin_id']) ? (int) $_POST['pin_id'] : 0;
        if ($boardId <= 0 || $pinId <= 0) {
            $this->redirect('board/detail', ['id' => $boardId, 'attach' => 'invalid']);
        }

        $db = Database::connection($this->config['db']);
        $boardModel = new Board($db);
        $canAttach = $boardModel->canAttachSavedPinToOwnedBoard($userId, $boardId, $pinId);
        if (!$canAttach) {
            $this->redirect('board/detail', ['id' => $boardId, 'attach' => 'denied']);
        }

        $attached = $boardModel->addPin($boardId, $pinId);
        $this->redirect('board/detail', ['id' => $boardId, 'attach' => $attached ? 'ok' : 'failed']);
    }
}
