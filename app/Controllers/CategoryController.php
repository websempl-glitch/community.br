<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;

class CategoryController
{
    public function showParent(string $slug): void
    {
        $db = Database::conn();
        
        // Ищем корневой раздел
        $stmt = $db->prepare('SELECT * FROM categories WHERE slug = ? AND parent_id IS NULL LIMIT 1');
        $stmt->execute([$slug]);
        $category = $stmt->fetch();

        if (!$category) {
            http_response_code(404);
            view('errors/404', ['pageTitle' => '404']);
            return;
        }

        // Получаем подразделы
        $stmt = $db->prepare('SELECT * FROM categories WHERE parent_id = ? ORDER BY name ASC');
        $stmt->execute([$category['id']]);
        $subcategories = $stmt->fetchAll();

        // Получаем ТОЛЬКО статьи, привязанные напрямую к этому разделу
        $articles = $this->getArticles($category['id'], false);

        view('categories/show', [
            'pageTitle' => $category['name'],
            'category' => $category,
            'subcategories' => $subcategories,
            'articles' => $articles,
            'breadcrumbs' => [
                ['name' => $category['name'], 'url' => '/category/' . $category['slug']]
            ],
        ]);
    }

    public function showSub(string $parentSlug, string $subSlug): void
    {
        $db = Database::conn();
        
        // Ищем родителя
        $stmt = $db->prepare('SELECT * FROM categories WHERE slug = ? AND parent_id IS NULL LIMIT 1');
        $stmt->execute([$parentSlug]);
        $parent = $stmt->fetch();

        if (!$parent) {
            http_response_code(404);
            view('errors/404', ['pageTitle' => '404']);
            return;
        }

        // Ищем подраздел
        $stmt = $db->prepare('SELECT * FROM categories WHERE slug = ? AND parent_id = ? LIMIT 1');
        $stmt->execute([$subSlug, $parent['id']]);
        $sub = $stmt->fetch();

        if (!$sub) {
            http_response_code(404);
            view('errors/404', ['pageTitle' => '404']);
            return;
        }

        // Получаем статьи только этого подраздела
        $articles = $this->getArticles($sub['id'], false);

        view('categories/show', [
            'pageTitle' => $sub['name'],
            'category' => $sub,
            'parent' => $parent,
            'subcategories' => [], // У подраздела нет своих подразделов
            'articles' => $articles,
            'breadcrumbs' => [
                ['name' => $parent['name'], 'url' => '/category/' . $parent['slug']],
                ['name' => $sub['name'], 'url' => '/category/' . $parent['slug'] . '/' . $sub['slug']],
            ],
        ]);
    }

    private function getArticles(int $categoryId, bool $includeSubs = false): array
    {
        $sql = 'SELECT a.slug, a.title, a.excerpt, a.published_at, a.views, a.pinned_category,
                       COALESCE(a.cover_image, c.image_path) AS display_image,
                       c.name AS category_name, c.slug AS category_slug,
                       c_parent.name AS parent_category_name, c_parent.slug AS parent_category_slug,
                       u.username AS author, u.deleted_at AS author_deleted_at,
                       (SELECT COUNT(*) FROM comments c2 WHERE c2.article_id = a.id AND c2.status = "visible") AS comments_count
                FROM articles a
                JOIN users u ON u.id = a.author_id
                LEFT JOIN categories c ON c.id = a.category_id
                LEFT JOIN categories c_parent ON c_parent.id = c.parent_id
                WHERE a.published_at IS NOT NULL';
        
        $params = [];
        if ($includeSubs) {
            $sql .= ' AND (a.category_id = ? OR a.category_id IN (SELECT id FROM categories WHERE parent_id = ?))';
            $params = [$categoryId, $categoryId];
        } else {
            $sql .= ' AND a.category_id = ?';
            $params = [$categoryId];
        }
        
        $sql .= ' ORDER BY a.pinned_category DESC, a.published_at DESC';
        
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}