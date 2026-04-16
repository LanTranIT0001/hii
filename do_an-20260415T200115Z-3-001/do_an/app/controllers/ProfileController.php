<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Board;
use App\Models\Pin;
use App\Models\User;
use Core\Controller;
use Core\Database;
use PDOException;

class ProfileController extends Controller
{
    public function showAction(): void
    {
        $viewerId = $this->requireAuth();
        $profileUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : $viewerId;
        if ($profileUserId <= 0) {
            $profileUserId = $viewerId;
        }

        $activeTab = isset($_GET['tab']) ? strtolower(trim((string) $_GET['tab'])) : 'pins';
        $allowedTabs = ['pins', 'boards', 'saved-pins', 'saved-boards'];
        if (!in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'pins';
        }

        $boardFilter = isset($_GET['board_filter']) ? strtolower(trim((string) $_GET['board_filter'])) : 'all';
        if (!in_array($boardFilter, ['all', 'public', 'private'], true)) {
            $boardFilter = 'all';
        }

        $db = Database::connection($this->config['db']);
        $userModel = new User($db);
        $pinModel = new Pin($db);
        $boardModel = new Board($db);

        $profileUser = $userModel->findById($profileUserId);
        if (!$profileUser) {
            $this->redirect('home/index');
        }

        try {
            $createdPins = $pinModel->listByUser($profileUserId);
        } catch (PDOException $e) {
            $createdPins = [];
        }
        try {
            $savedPins = $pinModel->getSavedPins($profileUserId);
        } catch (PDOException $e) {
            $savedPins = [];
        }
        try {
            $createdBoards = $boardModel->listByUserWithFilter($profileUserId, $boardFilter);
        } catch (PDOException $e) {
            $createdBoards = [];
        }
        if ($createdBoards !== []) {
            $boardIds = array_values(array_filter(array_map('intval', array_column($createdBoards, 'id')), static function (int $id): bool {
                return $id > 0;
            }));
            $previewMap = $boardModel->getPreviewImageUrlsForBoards($boardIds);
            foreach ($createdBoards as &$cb) {
                $bid = (int) ($cb['id'] ?? 0);
                $cb['preview_images'] = $previewMap[$bid] ?? [];
                $cb['user_id'] = $profileUserId;
            }
            unset($cb);
        }
        try {
            $savedBoards = $boardModel->listSavedBoardsByUser($profileUserId);
        } catch (PDOException $e) {
            $savedBoards = [];
        }

        $this->view('profile/show', [
            'profileUser' => $profileUser,
            'isOwnProfile' => $viewerId === $profileUserId,
            'activeTab' => $activeTab,
            'boardFilter' => $boardFilter,
            'createdPins' => $createdPins,
            'savedPins' => $savedPins,
            'createdBoards' => $createdBoards,
            'savedBoards' => $savedBoards,
            'followers' => $userModel->getFollowerCount($profileUserId),
            'following' => $userModel->getFollowingCount($profileUserId),
            'pinCount' => $pinModel->countByUser($profileUserId),
            'availableBoards' => $boardModel->listByUser($viewerId),
        ]);
    }

    public function updateAvatarAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('profile/show');
        }

        $userId = $this->requireAuth();
        if (!isset($_FILES['avatar']) || !is_array($_FILES['avatar'])) {
            $this->redirect('profile/show', ['avatar_error' => 1]);
        }

        $avatar = $_FILES['avatar'];
        if (($avatar['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->redirect('profile/show', ['avatar_error' => 1]);
        }

        $tmpName = (string) ($avatar['tmp_name'] ?? '');
        $mimeType = (string) (mime_content_type($tmpName) ?: '');
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
        ];

        if (!isset($allowedTypes[$mimeType])) {
            $this->redirect('profile/show', ['avatar_error' => 1]);
        }

        $uploadDir = __DIR__ . '/../../public/uploads/avatars';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            $this->redirect('profile/show', ['avatar_error' => 1]);
        }

        $filename = 'avatar_' . $userId . '_' . time() . '.' . $allowedTypes[$mimeType];
        $targetFile = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($tmpName, $targetFile)) {
            $this->redirect('profile/show', ['avatar_error' => 1]);
        }

        $avatarPath = 'uploads/avatars/' . $filename;
        $db = Database::connection($this->config['db']);
        $userModel = new User($db);
        if (!$userModel->updateAvatar($userId, $avatarPath)) {
            $this->redirect('profile/show', ['avatar_error' => 1]);
        }

        if (isset($_SESSION['user'])) {
            $_SESSION['user']['avatar'] = $avatarPath;
        }

        $this->redirect('profile/show', ['avatar_updated' => 1]);
    }

    public function updateProfileAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('profile/show');
        }

        $userId = $this->requireAuth();
        $name = trim((string) ($_POST['name'] ?? ''));
        $bio = trim((string) ($_POST['bio'] ?? ''));

        if ($name === '') {
            $this->redirect('profile/show', ['profile_error' => 1]);
        }

        $db = Database::connection($this->config['db']);
        $userModel = new User($db);
        if (!$userModel->updateProfile($userId, $name, $bio)) {
            $this->redirect('profile/show', ['profile_error' => 1]);
        }

        if (isset($_SESSION['user'])) {
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['bio'] = $bio;
        }

        $this->redirect('profile/show', ['profile_updated' => 1]);
    }

    public function createPinAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('profile/show', ['tab' => 'pins']);
        }

        $userId = $this->requireAuth();
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $categoryLabel = trim((string) ($_POST['category_label'] ?? ''));

        if ($title === '' || !isset($_FILES['image']) || !is_array($_FILES['image'])) {
            $this->redirect('profile/show', ['tab' => 'pins', 'create_pin_error' => 1]);
        }

        $image = $_FILES['image'];
        if (($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->redirect('profile/show', ['tab' => 'pins', 'create_pin_error' => 1]);
        }

        $tmpName = (string) ($image['tmp_name'] ?? '');
        $mimeType = (string) (mime_content_type($tmpName) ?: '');
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
        ];
        if (!isset($allowedTypes[$mimeType])) {
            $this->redirect('profile/show', ['tab' => 'pins', 'create_pin_error' => 1]);
        }

        $uploadDir = __DIR__ . '/../../public/uploads/pins';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            $this->redirect('profile/show', ['tab' => 'pins', 'create_pin_error' => 1]);
        }

        $filename = 'pin_' . $userId . '_' . time() . '.' . $allowedTypes[$mimeType];
        $targetFile = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($tmpName, $targetFile)) {
            $this->redirect('profile/show', ['tab' => 'pins', 'create_pin_error' => 1]);
        }

        $imageUrl = 'uploads/pins/' . $filename;
        $db = Database::connection($this->config['db']);
        $pinModel = new Pin($db);
        $created = $pinModel->createPin($userId, $title, $imageUrl, $description, $categoryLabel);
        if (!$created) {
            $this->redirect('profile/show', ['tab' => 'pins', 'create_pin_error' => 1]);
        }

        $this->redirect('profile/show', ['tab' => 'pins', 'create_pin_success' => 1]);
    }

    public function updatePinAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('profile/show', ['tab' => 'pins']);
        }

        $userId = $this->requireAuth();
        $pinId = isset($_POST['pin_id']) ? (int) $_POST['pin_id'] : 0;
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $categoryLabel = trim((string) ($_POST['category_label'] ?? ''));

        if ($pinId <= 0 || $title === '') {
            $this->redirect('profile/show', ['tab' => 'pins', 'update_pin_error' => 1]);
        }

        $db = Database::connection($this->config['db']);
        $pinModel = new Pin($db);
        $pin = $pinModel->findById($pinId);
        if (!$pin || (int) ($pin['user_id'] ?? 0) !== $userId) {
            $this->redirect('profile/show', ['tab' => 'pins', 'update_pin_error' => 1]);
        }

        $updated = $pinModel->updateOwnedPin($pinId, $userId, $title, $description, $categoryLabel);
        if (!$updated) {
            $this->redirect('profile/show', ['tab' => 'pins', 'update_pin_error' => 1]);
        }

        $this->redirect('profile/show', ['tab' => 'pins', 'update_pin_success' => 1]);
    }

    public function deletePinAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('profile/show', ['tab' => 'pins']);
        }

        $userId = $this->requireAuth();
        $pinId = isset($_POST['pin_id']) ? (int) $_POST['pin_id'] : 0;
        if ($pinId <= 0) {
            $this->redirect('profile/show', ['tab' => 'pins', 'delete_pin_error' => 1]);
        }

        $db = Database::connection($this->config['db']);
        $pinModel = new Pin($db);
        $deleted = $pinModel->deleteOwnedPin($pinId, $userId);
        if (!$deleted) {
            $this->redirect('profile/show', ['tab' => 'pins', 'delete_pin_error' => 1]);
        }

        $this->redirect('profile/show', ['tab' => 'pins', 'delete_pin_success' => 1]);
    }

    public function createBoardAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('profile/show', ['tab' => 'boards']);
        }

        $userId = $this->requireAuth();
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $privacy = strtoupper(trim((string) ($_POST['privacy'] ?? 'PUBLIC')));
        if ($privacy !== 'PRIVATE') {
            $privacy = 'PUBLIC';
        }

        if ($name === '') {
            $this->redirect('profile/show', ['tab' => 'boards', 'create_board_error' => 1]);
        }

        $db = Database::connection($this->config['db']);
        $boardModel = new Board($db);
        if (!$boardModel->create($userId, $name, $description, $privacy)) {
            $this->redirect('profile/show', ['tab' => 'boards', 'create_board_error' => 1]);
        }

        $this->redirect('profile/show', ['tab' => 'boards', 'create_board_success' => 1]);
    }

    public function deleteBoardAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('profile/show', ['tab' => 'boards']);
        }

        $userId = $this->requireAuth();
        $boardId = isset($_POST['board_id']) ? (int) $_POST['board_id'] : 0;
        if ($boardId <= 0) {
            $this->redirect('profile/show', ['tab' => 'boards', 'delete_board_error' => 1]);
        }

        $db = Database::connection($this->config['db']);
        $boardModel = new Board($db);
        $deleted = $boardModel->deleteOwnedBoard($boardId, $userId);
        if (!$deleted) {
            $this->redirect('profile/show', ['tab' => 'boards', 'delete_board_error' => 1]);
        }

        $this->redirect('profile/show', ['tab' => 'boards', 'delete_board_success' => 1]);
    }
}
