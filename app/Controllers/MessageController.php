<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Markdown;

class MessageController
{
    // Список входящих и отправленных
    public function inbox(string $username): void
    {
        $user = $this->requireOwnProfile($username);
        $tab = $_GET['tab'] ?? 'inbox';
        $q = trim($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $db = Database::conn();

        // Счетчики для вкладок (они не учитывают поиск, это нормально)
        $stmt = $db->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND deleted_by_receiver = 0 AND is_read = 0');
        $stmt->execute([$user['id']]);
        $unreadCount = (int)$stmt->fetchColumn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND deleted_by_receiver = 0');
        $stmt->execute([$user['id']]);
        $inboxCount = (int)$stmt->fetchColumn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM messages WHERE sender_id = ? AND deleted_by_sender = 0');
        $stmt->execute([$user['id']]);
        $sentCount = (int)$stmt->fetchColumn();

        // Формируем условия
        $params = [$user['id']];
        if ($tab === 'sent') {
            $where = 'm.sender_id = ? AND m.deleted_by_sender = 0';
            $joinUser = 'receiver_id';
        } else {
            $where = 'm.receiver_id = ? AND m.deleted_by_receiver = 0';
            $joinUser = 'sender_id';
        }

        if ($q !== '') {
            // Ищем и по тексту сообщения, и по имени собеседника
            $where .= ' AND (m.body LIKE ? OR u.username LIKE ?)';
            $params[] = "%$q%";
            $params[] = "%$q%";
        }

        // Подсчет общего количества (используем $params)
        $stmt = $db->prepare(
            "SELECT COUNT(*) 
             FROM messages m 
             JOIN users u ON u.id = m.$joinUser 
             WHERE $where"
        );
        $stmt->execute($params);
        $totalCount = (int)$stmt->fetchColumn();
        $totalPages = (int)ceil($totalCount / $limit);

        // Получение списка (тоже используем $params + добавили read_at)
        $stmt = $db->prepare(
            "SELECT m.id, m.body, m.is_read, m.read_at, m.created_at, 
                    u.username AS peer_username, u.avatar_path AS peer_avatar
             FROM messages m
             JOIN users u ON u.id = m.$joinUser
             WHERE $where
             ORDER BY m.created_at DESC
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        $this->renderMessagesLayout($username, 'messages', [
            'pageTitle' => 'Сообщения',
            'items' => $items,
            'tab' => $tab,
            'q' => $q,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'unreadCount' => $unreadCount,
            'inboxCount' => $inboxCount,
            'sentCount' => $sentCount,
        ]);
    }

    // Просмотр одного сообщения
        public function view(string $username, string $id): void
    {
        $user = $this->requireOwnProfile($username);
        $db = Database::conn();
        
        $stmt = $db->prepare(
            'SELECT m.*, 
                    s.username AS sender_username, s.avatar_path AS sender_avatar,
                    r.username AS receiver_username
             FROM messages m
             JOIN users s ON s.id = m.sender_id
             JOIN users r ON r.id = m.receiver_id
             WHERE m.id = ? LIMIT 1'
        );
        $stmt->execute([(int)$id]);
        $msg = $stmt->fetch();

        // 404 — только если сообщения нет вообще или это не наша переписка
        if (!$msg || ((int)$msg['sender_id'] !== (int)$user['id'] && (int)$msg['receiver_id'] !== (int)$user['id'])) {
            http_response_code(404);
            view('errors/404', ['pageTitle' => '404']);
            return;
        }

        // Проверяем, удалено ли сообщение именно для этого пользователя
        $isSender = (int)$msg['sender_id'] === (int)$user['id'];
        $isReceiver = (int)$msg['receiver_id'] === (int)$user['id'];
        $deletedForMe = ($isSender && (int)$msg['deleted_by_sender'] === 1) 
                     || ($isReceiver && (int)$msg['deleted_by_receiver'] === 1);

        // Общие данные для шаблона
        $peerId = $isSender ? (int)$msg['receiver_id'] : (int)$msg['sender_id'];
        $peerName = $isSender ? $msg['receiver_username'] : $msg['sender_username'];

        if ($deletedForMe) {
            // Помечаем уведомление прочитанным (чтобы счётчик не горел)
            $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND message_id = ?')
               ->execute([$user['id'], $msg['id']]);

            $this->renderMessagesLayout($user['username'], 'messages', [
                'pageTitle'   => 'Сообщение удалено',
                'message'     => $msg,
                'viewMode'    => true,
                'isDeleted'   => true,
                'peerId'      => $peerId,
                'peerName'    => $peerName,
                'isBlockedByMe' => false,
                'isBlockedMe'   => false,
            ]);
            return;
        }

        // Блокировки
        $stmt = $db->prepare('SELECT 1 FROM message_blocks WHERE blocker_id = ? AND blocked_id = ?');
        $stmt->execute([$user['id'], $peerId]);
        $isBlockedByMe = (bool)$stmt->fetchColumn();

        $stmt = $db->prepare('SELECT 1 FROM message_blocks WHERE blocker_id = ? AND blocked_id = ?');
        $stmt->execute([$peerId, $user['id']]);
        $isBlockedMe = (bool)$stmt->fetchColumn();

        // Помечаем прочитанным
        if ($isReceiver && !$msg['is_read']) {
            $db->prepare('UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = ?')->execute([$msg['id']]);
            $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND message_id = ?')->execute([$user['id'], $msg['id']]);
            $msg['is_read'] = 1;
        }

        $this->renderMessagesLayout($user['username'], 'messages', [
            'pageTitle' => 'Сообщение от ' . $msg['sender_username'],
            'message' => $msg,
            'bodyHtml' => Markdown::toHtml($msg['body']),
            'viewMode' => true,
            'peerId' => $peerId,
            'peerName' => $peerName,
            'isBlockedByMe' => $isBlockedByMe,
            'isBlockedMe' => $isBlockedMe,
        ]);
    }

    // Форма нового сообщения
    public function create(string $username): void
    {
        $user = Auth::user();
        if (!$user) redirect('/login');

        $db = Database::conn();
        $stmt = $db->prepare('SELECT id, username FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$username]);
        $receiver = $stmt->fetch();

        if (!$receiver || (int)$receiver['id'] === (int)$user['id']) {
            redirect('/u/' . $username);
        }

        // Проверяем, не заблокировал ли он меня
        $stmt = $db->prepare('SELECT 1 FROM message_blocks WHERE blocker_id = ? AND blocked_id = ?');
        $stmt->execute([$receiver['id'], $user['id']]);
        $isBlockedMe = (bool)$stmt->fetchColumn();

        // Ищем сообщение для цитирования
        $replyTo = null;
        if (!empty($_GET['reply_to'])) {
            $replyId = (int)$_GET['reply_to'];
            $stmt = $db->prepare(
                'SELECT m.id, m.body, m.created_at, u.username AS sender_name 
                 FROM messages m
                 JOIN users u ON u.id = m.sender_id
                 WHERE m.id = ? AND (m.sender_id = ? OR m.receiver_id = ?) LIMIT 1'
            );
            $stmt->execute([$replyId, $user['id'], $user['id']]);
            $replyTo = $stmt->fetch() ?: null;
        }

        $this->renderMessagesLayout($user['username'], 'messages', [
            'pageTitle' => 'Написать сообщение',
            'receiver' => $receiver,
            'replyTo' => $replyTo, // <--- Передаем в шаблон
            'isBlockedMe' => $isBlockedMe,
            'old' => [],
            'errors' => [],
        ]);
    }

    // Отправка
    public function store(): void
    {
        csrf_check($_POST['_csrf'] ?? null);
        $user = Auth::user();
        if (!$user) redirect('/login');

        $receiverId = (int)($_POST['receiver_id'] ?? 0);
        $body = trim($_POST['body'] ?? '');

        $db = Database::conn();
        
        // Проверка на блокировку
        $stmt = $db->prepare('SELECT 1 FROM message_blocks WHERE blocker_id = ? AND blocked_id = ?');
        $stmt->execute([$receiverId, $user['id']]);
        if ($stmt->fetch()) {
            exit('Вы не можете отправлять сообщения этому пользователю.');
        }

        $stmt = $db->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$receiverId]);
        $receiverName = $stmt->fetchColumn();

        if (!$receiverName || $body === '') {
            redirect('/u/' . $receiverName . '/message');
        }

        $stmt = $db->prepare('INSERT INTO messages (sender_id, receiver_id, body) VALUES (?, ?, ?)');
        $stmt->execute([$user['id'], $receiverId, $body]);
        $msgId = (int)$db->lastInsertId();

        // Создаем уведомление
        $db->prepare(
            'INSERT INTO notifications (user_id, actor_id, type, message_id) 
             VALUES (?, ?, "message", ?)'
        )->execute([$receiverId, $user['id'], $msgId]);

        redirect('/u/' . $user['username'] . '/messages/view/' . $msgId);
    }

    // Массовое удаление
    public function bulkDelete(): void
    {
        csrf_check_any();
        $user = Auth::user();
        if (!$user) json_response(['ok' => false], 401);

        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) json_response(['ok' => true]);

        $db = Database::conn();
        $in = implode(',', array_fill(0, count($ids), '?'));

        // Удаляем из входящих и отправленных для текущего пользователя
        $db->prepare("UPDATE messages SET deleted_by_receiver = 1 WHERE receiver_id = ? AND id IN ($in)")
           ->execute(array_merge([$user['id']], $ids));
		   
		// Удаляем уведомления, связанные с этими сообщениями
        $db->prepare("DELETE FROM notifications WHERE message_id IN ($in)")
           ->execute($ids);

        $db->prepare("UPDATE messages SET deleted_by_sender = 1 WHERE sender_id = ? AND id IN ($in)")
           ->execute(array_merge([$user['id']], $ids));

        json_response(['ok' => true]);
    }

    // Блокировка/разблокировка
    public function toggleBlock(string $id): void
    {
        csrf_check_any();
        $user = Auth::user();
        if (!$user) json_response(['ok' => false], 401);

        $targetId = (int)$id;
        if ($targetId === (int)$user['id']) json_response(['ok' => false], 400);

        $db = Database::conn();
        $stmt = $db->prepare('SELECT 1 FROM message_blocks WHERE blocker_id = ? AND blocked_id = ?');
        $stmt->execute([$user['id'], $targetId]);

        if ($stmt->fetch()) {
            $db->prepare('DELETE FROM message_blocks WHERE blocker_id = ? AND blocked_id = ?')->execute([$user['id'], $targetId]);
            json_response(['ok' => true, 'blocked' => false]);
        } else {
            $db->prepare('INSERT INTO message_blocks (blocker_id, blocked_id) VALUES (?, ?)')->execute([$user['id'], $targetId]);
            json_response(['ok' => true, 'blocked' => true]);
        }
    }
	
	// Пожаловаться на сообщение
    public function report(string $id): void
    {
        csrf_check_any();
        $user = Auth::user();
        if (!$user) json_response(['ok' => false, 'error' => 'Auth required'], 401);

        $msgId = (int)$id;
        $db = Database::conn();

        // Проверяем, что сообщение существует и пользователь является получателем
        $stmt = $db->prepare('SELECT receiver_id FROM messages WHERE id = ? LIMIT 1');
        $stmt->execute([$msgId]);
        $receiverId = (int)$stmt->fetchColumn();

        if ($receiverId !== (int)$user['id']) {
            json_response(['ok' => false, 'error' => 'Вы можете жаловаться только на входящие сообщения'], 403);
        }

        try {
            $db->prepare('INSERT IGNORE INTO message_reports (message_id, user_id) VALUES (?, ?)')
               ->execute([$msgId, $user['id']]);
            json_response(['ok' => true]);
        } catch (\Throwable $e) {
            json_response(['ok' => false, 'error' => 'Ошибка при отправке жалобы'], 500);
        }
    }

    private function requireOwnProfile(string $username): array
    {
        $user = Auth::user();
        if (!$user || $user['username'] !== $username) redirect('/login');
        return $user;
    }

    private function renderMessagesLayout(string $username, string $tab, array $data = []): void
    {
        $user = Auth::user();
        $db = Database::conn();

        // Собираем настоящую статистику, чтобы меню не пропадало
        $stats = [
            'comments' => (int)$db->query("SELECT COUNT(*) FROM comments WHERE user_id = {$user['id']} AND status = 'visible'")->fetchColumn(),
            'articles' => (int)$db->query("SELECT COUNT(*) FROM articles WHERE author_id = {$user['id']} AND published_at IS NOT NULL")->fetchColumn(),
            'drafts'   => (int)$db->query("SELECT COUNT(*) FROM articles WHERE author_id = {$user['id']} AND published_at IS NULL")->fetchColumn(),
            'bookmarks'=> (int)$db->query("SELECT COUNT(*) FROM bookmarks WHERE user_id = {$user['id']}")->fetchColumn(),
            'subs'     => (int)$db->query("SELECT COUNT(*) FROM user_follows WHERE follower_id = {$user['id']}")->fetchColumn(),
            'followers'=> (int)$db->query("SELECT COUNT(*) FROM user_follows WHERE followed_id = {$user['id']}")->fetchColumn(),
            'karma'    => \App\Karma::forUser((int)$user['id']),
        ];
        
        $statusKey = \App\UserStatus::keyByCount($stats['comments']);

        view('users/layout', array_merge([
            'pageTitle'      => 'Сообщения',
            'profileUser'    => $user,
            'stats'          => $stats,
            'statusKey'      => $statusKey,
            'isOwnProfile'   => true,
            'isFollowing'    => false,
            'activeTab'      => 'messages',
            'achievements'   => \App\Achievement::getForUser((int)$user['id']),
        ], $data));
    }
}