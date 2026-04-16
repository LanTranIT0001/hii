<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Pin;
use Core\Controller;
use Core\Database;

class HomeController extends Controller
{
    public function indexAction(): void
    {
        $db = Database::connection($this->config['db']);
        $pinModel = new Pin($db);

        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $keyword = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        $activeCategory = isset($_GET['category']) ? trim((string) $_GET['category']) : '';
        $perPage = (int) $this->config['pagination']['per_page'];

        $totalItems = $pinModel->countAll($keyword, $activeCategory);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $pins = $pinModel->feed($offset, $perPage, $keyword, $activeCategory);
        $smartCategories = $pinModel->listSmartCategories(14);
        $savedPinIds = [];
        $userId = $this->currentUserId();
        if ($userId !== null && $pins !== []) {
            $savedPinIds = $pinModel->getSavedPinIdsForUser($userId, array_column($pins, 'id'));
        }

        $this->view('pins/feed', [
            'pins' => $pins,
            'keyword' => $keyword,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'savedPinIds' => $savedPinIds,
            'smartCategories' => $smartCategories,
            'activeCategory' => $activeCategory,
        ]);
    }
}
