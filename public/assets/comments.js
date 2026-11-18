document.addEventListener('alpine:init', () => {
  Alpine.data('commentsApp', () => ({
    articleId: null,
    comments: [],
    loading: true,
    submitting: false,
    error: '',
    newBody: '',
	replyBody: '',
    replyTo: null,
    replyTargetId: null,
    replyToUser: null,
    isLoggedIn: false,
    currentUser: null,
    canModerate: false,

    // Подписка
    isSubscribed: false,
	
	// Комментарии открыты/закрыты
    commentsOpen: true,
    commentsClosedReason: null,

    // Тост
    toast: { message: '', timer: null },
	
	// Модалка жалобы
    reportModal: {
      open: false,
      commentId: null,
      reason: 'spam',
      note: '',
      busy: false,
      error: '',
    },
	
    formImages: [],
    uploading: false,
    dragOver: false,

    lightbox: {
      open: false,
      images: [],
      index: 0,
    },

    editingId: null,
    editingBody: '',
	editingImages: [],
	editingNewImages: [],

    outlineOpen: false,
    sortBy: 'recent',
    lastSeen: 0,
	
	// Поиск по текущему обсуждению
    discussionSearch: '',

    // Polling
    lastSyncAt: null,
    pendingCount: 0,
    pendingItems: [],
    pollTimer: null,

    // Панель со списком новых
    updatesPanelOpen: false,

    // Свежесть
    serverNow: null,
    freshThreshold: null,

    menu: {
      open: false,
      x: 0,
      y: 0,
      comment: null,
      root: null,
      isOwn: false,
    },
	
	// Плавающая кнопка цитирования
    quoteBtn: {
      visible: false,
      x: 0,
      y: 0,
      text: '',
      target: null,
      root: null,
      isArticle: false,
    },

    get totalLabel() {
      if (this.loading) return '';
      return this.comments.length + ' ' + this.plural(
        this.comments.length, 'комментарий', 'комментария', 'комментариев'
      );
    },

    get outline() {
      if (!this.comments.length) return [];

      const items = this.comments.map(c => {
        const preview = this.stripHtml(c.body_html).slice(0, 80);
        const lastTs = this.serverTimeToMs(c.last_activity || c.created_at);
        const isNew = !c.is_deleted && this.lastSeen > 0 && lastTs > this.lastSeen;

        return {
          id: c.id,
          username: c.username,
          preview,
          time_ago: c.last_activity_ago || c.time_ago,
          reply_count: c.replyCount || 0,
          is_pinned: c.is_pinned,
          isNew,
          sortTs: lastTs,
        };
      });

      if (this.sortBy === 'hot') {
        items.sort((a, b) => {
          if (a.is_pinned !== b.is_pinned) return a.is_pinned ? -1 : 1;
          if (b.reply_count !== a.reply_count) return b.reply_count - a.reply_count;
          return b.sortTs - a.sortTs;
        });
      } else {
        items.sort((a, b) => {
          if (a.is_pinned !== b.is_pinned) return a.is_pinned ? -1 : 1;
          return b.sortTs - a.sortTs;
        });
      }
      return items;
    },
	
	get filteredComments() {
      const q = this.discussionSearch.trim().toLowerCase();
      if (q.length < 2) return this.comments;
      return this.comments.filter(c => {
        const text = (c.body_raw || '').toLowerCase();
        return text.includes(q);
      });
    },

    plural(n, one, few, many) {
      n = Math.abs(n) % 100;
      if (n > 10 && n < 20) return many;
      n = n % 10;
      if (n === 1) return one;
      if (n >= 2 && n <= 4) return few;
      return many;
    },

    serverTimeToMs(s) {
      if (!s) return 0;
      const parts = String(s).split(' ');
      if (parts.length !== 2) return 0;
      const [d, t] = parts;
      const [y, mo, da] = d.split('-').map(Number);
      const [h, mi, se] = t.split(':').map(Number);
      return new Date(y, mo - 1, da, h, mi, se).getTime();
    },
	
	reactionEmoji(r) {
      return { 
        like: '👍', 
        love: '❤️', 
        funny: '😂', 
        sad: '😢', 
        angry: '😡', 
        poop: '💩' 
      }[r] || '👍';
    },

    totalReactions(c) {
      if (!c.reactions) return 0;
      return Object.values(c.reactions).reduce((a, b) => a + b, 0);
    },

    async toggleReaction(c, r) {
      if (!this.isLoggedIn) {
        window.location.href = '/login';
        return;
      }
      try {
        const fd = new FormData();
        fd.append('reaction', r);
        const resp = await fetch(`/api/comments/${c.id}/react`, {
          method: 'POST',
          headers: { 'X-CSRF-Token': this.csrf() },
          body: fd,
        });
        const data = await resp.json();
        if (data.ok) {
          c.reactions = data.reactions;
          c.my_reaction = data.my_reaction;
        }
      } catch {}
    },

    init() {
      const root = document.querySelector('.layout-grid');
      this.articleId = parseInt(root.dataset.articleId, 10);
      this.canModerate = root.dataset.canModerate === '1';

      const u = document.querySelector('.nav-user');      

      // Серверные данные
      const initial = window.__commentsInitial || {};
	  this.currentUser = initial.currentUser || null;
	  this.isLoggedIn = !!this.currentUser;
      this.pendingCount = initial.newCount || 0;
      this.pendingItems = initial.newItems || [];
      this.isSubscribed = initial.isSubscribed || false;
      this.serverNow = initial.serverTime || null;
      this.commentsOpen = initial.commentsOpen !== false;
      this.commentsClosedReason = initial.commentsClosedReason || null;

      // Берем время последнего визита с сервера
      this.lastSeen = initial.lastSeenAt ? this.serverTimeToMs(initial.lastSeenAt) : 0;

      this.load().then(() => {
        this.highlightAll();
        this.$nextTick(() => this.handleHash());
        this.startPolling();
      });

      window.addEventListener('hashchange', () => this.handleHash());

      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
          this.checkUpdates();
        }
      });

      document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-code-copy]');
        if (!btn) return;
        const wrap = btn.closest('.code-wrap');
        const code = wrap?.querySelector('code');
        if (!code) return;
        navigator.clipboard.writeText(code.textContent).then(() => {
          const old = btn.textContent;
          btn.textContent = 'Скопировано';
          btn.classList.add('is-copied');
          setTimeout(() => {
            btn.textContent = old;
            btn.classList.remove('is-copied');
          }, 1500);
        });
      });
	  
	  // Отслеживаем выделение текста
      document.addEventListener('mouseup', (e) => this.checkSelection(e));
      document.addEventListener('keyup', (e) => {
        if (e.shiftKey || e.key === 'Shift') this.checkSelection(e);
      });

      // Прячем при клике мимо, скролле или правой кнопке мыши
      document.addEventListener('mousedown', (e) => {
        if (!e.target.closest('.floating-quote-btn')) {
          this.quoteBtn.visible = false;
        }
      });
      document.addEventListener('contextmenu', () => {
        this.quoteBtn.visible = false;
      });
      document.addEventListener('copy', () => {
        this.quoteBtn.visible = false;
      });
      document.addEventListener('scroll', () => {
        this.quoteBtn.visible = false;
      }, { passive: true });
    },

    csrf() {
      return document.querySelector('meta[name=csrf-token]').content;
    },

    handleEscape() {
      if (this.lightbox.open) { this.closeLightbox(); return; }
      if (this.outlineOpen)   { this.outlineOpen = false; return; }
      this.closeMenu();
      this.cancelEdit();
    },
	
	checkSelection(e) {
      if (!this.isLoggedIn) return;
	  
	  // Игнорируем правую кнопку мыши и колесико
      if (e && e.type === 'mouseup' && e.button !== 0) return;

      // Даём браузеру миллисекунду, чтобы он зафиксировал выделение
      setTimeout(() => {
        const sel = window.getSelection();
        const text = sel.toString().trim();
        
        if (!text) {
          this.quoteBtn.visible = false;
          return;
        }

        let node = sel.anchorNode;
        let isArticle = false;
        let targetComment = null;
        let rootComment = null;

        // Ищем, где именно находится выделение (в статье или в комментарии)
        while (node && node !== document.body) {
          if (node.classList && node.classList.contains('comment-body')) {
            const commentEl = node.closest('.comment');
            if (commentEl) {
              const commentId = parseInt(commentEl.id.replace('c-', ''), 10);
              targetComment = this.comments.find(c => c.id === commentId) || 
                              this.comments.flatMap(c => c.replies || []).find(r => r.id === commentId);
              
              // Определяем, к какому корневому относится этот ответ
              if (targetComment) {
                rootComment = this.comments.find(c => c.id === targetComment.id) || 
                              this.comments.find(c => c.replies?.some(r => r.id === commentId));
              }
            }
            break;
          }
          if (node.classList && node.classList.contains('article-body')) {
            isArticle = true;
            break;
          }
          node = node.parentNode;
        }

        if (!targetComment && !isArticle) {
          this.quoteBtn.visible = false;
          return;
        }

        // Позиционируем кнопку над выделением
        const range = sel.getRangeAt(0);
        const rect = range.getBoundingClientRect();

        this.quoteBtn = {
          visible: true,
          x: rect.left + rect.width / 2 + window.scrollX,
          y: rect.top + window.scrollY - 40,
          text: text,
          target: targetComment,
          root: rootComment,
          isArticle: isArticle,
        };
      }, 10);
    },

    handleQuoteClick() {
      if (!this.quoteBtn.visible) return;

      const quoted = this.quoteBtn.text.split('\n').map(l => '> ' + l).join('\n') + '\n\n';
      const target = this.quoteBtn.target;

      if (target) {
        // Цитируем комментарий (нужно открыть форму ответа)
        const root = this.quoteBtn.root;
        if (root && root.id !== target.id) {
          if (!root.repliesOpen) this.toggleReplies(root);
          this.replyTo = root.id;
          this.replyTargetId = target.id;
          this.replyToUser = target.username;
          
          if (!this.replyBody.includes('@' + target.username)) {
            this.replyBody = this.replyBody + '@' + target.username + ', \n\n' + quoted;
          } else {
            this.replyBody = this.replyBody + quoted;
          }
        } else {
          this.replyTo = target.id;
          this.replyTargetId = target.id;
          this.replyToUser = target.username;
          this.replyBody = this.replyBody + quoted;
        }

        this.$nextTick(() => {
          if (window.initMarkdownEditors) window.initMarkdownEditors(document);
          const form = document.querySelector('.comment-reply-form textarea');
          if (form) {
            form.focus();
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        });
      } else {
        // Цитируем из статьи — пишем в корневую форму
        this.newBody = this.newBody + quoted;
        
        this.$nextTick(() => {
          const form = document.querySelector('.comment-form textarea');
          if (form) {
            form.focus();
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        });
      }

      this.quoteBtn.visible = false;
      window.getSelection().removeAllRanges();
    },
	
	openReport(commentId) {
      this.reportModal.open = true;
      this.reportModal.commentId = commentId;
      this.reportModal.reason = 'spam';
      this.reportModal.note = '';
      this.reportModal.error = '';
      this.closeMenu();
    },

    closeReport() {
      this.reportModal.open = false;
    },

    async submitReport() {
      if (this.reportModal.busy) return;
      this.reportModal.busy = true;
      this.reportModal.error = '';

      const fd = new FormData();
      fd.append('reason', this.reportModal.reason);
      fd.append('note', this.reportModal.note);

      try {
        const r = await fetch(`/api/comments/${this.reportModal.commentId}/report`, {
          method: 'POST',
          headers: { 'X-CSRF-Token': this.csrf() },
          body: fd,
        });
        const data = await r.json();
        if (!data.ok) throw new Error(data.error || 'Ошибка');

        this.showToast('Жалоба отправлена модератору');
        this.closeReport();
      } catch (e) {
        this.reportModal.error = e.message;
      } finally {
        this.reportModal.busy = false;
      }
    },

    stripHtml(html) {
      const spaced = (html || '')
        .replace(/<\s*\/?\s*(h[1-6]|p|div|blockquote|pre|ul|ol|li|br|hr|table|tr|td|th|figure|figcaption|img)\b[^>]*\/?>/gi, ' ');
      const tmp = document.createElement('div');
      tmp.innerHTML = spaced;
      return (tmp.textContent || '')
        .replace(/\u00a0/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
    },

    highlightAll() {
      if (typeof hljs === 'undefined') return;
      document.querySelectorAll('.comment-body code[class*="language-"]').forEach((el) => {
        if (el.dataset.highlighted === 'yes') return;
        try { hljs.highlightElement(el); } catch {}
      });
    },

    showToast(message) {
      this.toast.message = message;
      if (this.toast.timer) clearTimeout(this.toast.timer);
      this.toast.timer = setTimeout(() => {
        this.toast.message = '';
      }, 5000);
    },

    /* ─── Свежесть комментариев ────────────────────── */

    isFresh(c) {
      if (this.pendingItems.some(it => it.id === c.id)) return true;
      if (this.freshThreshold && this.serverTimeToMs(c.created_at) > this.freshThreshold) {
        return true;
      }
      return false;
    },

    markFresh(threshold) {
      this.freshThreshold = threshold;
      if (this._freshTimer) clearTimeout(this._freshTimer);
      this._freshTimer = setTimeout(() => {
        this.freshThreshold = null;
      }, 5000);
    },

    /* ─── Polling новых комментариев ─────────────────── */

    startPolling() {
      this.stopPolling();
      if (!this.commentsOpen) return; // нет смысла опрашивать закрытое обсуждение
      this.pollTimer = setInterval(() => {
        if (document.visibilityState === 'visible') {
          this.checkUpdates();
        }
      }, 30000);
    },

    stopPolling() {
      if (this.pollTimer) {
        clearInterval(this.pollTimer);
        this.pollTimer = null;
      }
    },

    async checkUpdates() {
      if (this.loading) return;
      try {
        let url = `/api/articles/${this.slugFromUrl()}/updates`;
        if (!this.isLoggedIn && this.lastSyncAt) {
          url += `?since=${encodeURIComponent(this.lastSyncAt)}`;
        }
        const r = await fetch(url);
        const data = await r.json();
        if (!data.ok) return;

        this.pendingCount = data.count || 0;
        if (Array.isArray(data.items)) {
          this.pendingItems = data.items;
        }

        if (this.pendingCount === 0 && data.server_time) {
          this.lastSyncAt = data.server_time;
        }
      } catch {}
    },

    openUpdatesPanel() {
      // Гостю список не покажем — просто тихо обновимся
      if (!this.isLoggedIn) {
        this.reloadAll().then(() => this.$nextTick(() => this.highlightAll()));
        return;
      }
      if (this.pendingCount === 0 && this.pendingItems.length === 0) {
        this.checkUpdates();
        return;
      }
      this.updatesPanelOpen = true;
    },

    async goToUpdate(item) {
      // 1. Помечаем прочитанным только этот комментарий
      try {
        await fetch(`/api/comments/${item.id}/read`, {
          method: 'POST',
          headers: { 'X-CSRF-Token': this.csrf() },
        });
      } catch {}

      // 2. Перезагружаем список комментариев — чтобы нужный точно был в DOM
      await this.reloadAll();

      // 3. Обновляем панель
      await this.checkUpdates();

      if (this.pendingCount === 0) {
        this.updatesPanelOpen = false;
      }

      // 4. Скролл, раскрытие ветки, подсветка
      await this.revealAndScrollTo(item.id);
    },

    async markAllRead() {
      try {
        const r = await fetch(`/api/articles/${this.articleId}/mark-read`, {
          method: 'POST',
          headers: { 'X-CSRF-Token': this.csrf() },
        });
        const data = await r.json();
        if (data.ok && data.last_seen_at) {
          this.lastSeen = this.serverTimeToMs(data.last_seen_at);
          try {
            localStorage.setItem(
              'outline_last_seen_' + this.articleId,
              this.lastSeen.toString()
            );
          } catch {}
        }
      } catch {}

      this.pendingCount = 0;
      this.pendingItems = [];
      this.updatesPanelOpen = false;

      await this.reloadAll();
      this.$nextTick(() => this.highlightAll());
    },

    /* ─── Подписка / отписка ───────────────────────── */

    async unsubscribe() {
      if (!confirm('Отписаться от обсуждения этой статьи?')) return;
      try {
        const r = await fetch(`/api/articles/${this.articleId}/unfollow`, {
          method: 'POST',
          headers: { 'X-CSRF-Token': this.csrf() },
        });
        const data = await r.json();
        if (data.ok) {
          this.isSubscribed = false;
          this.showToast('Вы отписались от обсуждения');
          // Пересчитываем список — теперь только «вам»
          await this.checkUpdates();
          // Если после фильтра ничего не осталось — закрываем панель
          if (this.pendingCount === 0) {
            this.updatesPanelOpen = false;
          }
        }
      } catch {}
    },

    /* ─── Загрузка ──────────────────────────────────── */

    async load() {
      this.loading = true;
      try {
        const r = await fetch(`/api/articles/${this.slugFromUrl()}/comments`);
        const data = await r.json();
        if (!data.ok) throw new Error(data.error || 'Ошибка загрузки');
        this.comments = data.comments.map(c => ({
          ...c,
          replyCount: c.reply_count ?? 0,
          images: c.images || [],
          reactions: c.reactions || {},
          my_reaction: c.my_reaction || null,
          last_activity: c.last_activity || c.created_at,
          last_activity_ago: c.last_activity_ago || c.time_ago,
          replies: [],
          repliesOpen: false,
          repliesLoaded: false,
          repliesLoading: false,
        }));
        if (data.server_time) this.lastSyncAt = data.server_time;
      } catch (e) {
        this.error = e.message;
      } finally {
        this.loading = false;
      }
    },

    slugFromUrl() {
      const m = location.pathname.match(/^\/article\/([^/]+)/);
      return m ? m[1] : '';
    },

    async toggleReplies(c) {
      if (c.repliesOpen) { c.repliesOpen = false; return; }
      if (c.repliesLoaded) { c.repliesOpen = true; return; }
      c.repliesLoading = true;
      c.repliesOpen = true;
      try {
        const r = await fetch(`/api/comments/${c.id}/replies`);
        const data = await r.json();
        if (data.ok) {
          c.replies = data.replies;
          c.repliesLoaded = true;
          c.replyCount = data.replies.length;
          this.$nextTick(() => this.highlightAll());
        }
      } finally {
        c.repliesLoading = false;
      }
    },

    async revealAndScrollTo(id) {
      const root = this.comments.find(c => c.id === id);
      if (root) { this.scrollToAndHighlight(id); return; }

      const rootWithReply = this.comments.find(c =>
        c.repliesLoaded && c.replies && c.replies.some(r => r.id === id)
      );
      if (rootWithReply) {
        if (!rootWithReply.repliesOpen) await this.toggleReplies(rootWithReply);
        this.$nextTick(() => this.scrollToAndHighlight(id));
        return;
      }

      try {
        const r = await fetch(`/api/comments/${id}`);
        const data = await r.json();
        if (!data.ok) return;
        const parentRoot = this.comments.find(c => c.id === data.comment.root_id);
        if (!parentRoot) return;
        if (!parentRoot.repliesOpen) await this.toggleReplies(parentRoot);
        this.$nextTick(() => this.scrollToAndHighlight(id));
      } catch {}
    },

    startReply(rootComment, target = null) {
      this.replyTo = rootComment.id;
      this.replyTargetId = target ? target.id : rootComment.id;
      this.replyToUser = target ? target.username : rootComment.username;
      this.replyBody = ''; // <--- Меняем на replyBody
      this.error = '';
      this.$nextTick(() => {
        if (window.initMarkdownEditors) window.initMarkdownEditors(document);
        const form = document.querySelector(`#c-${rootComment.id} .comment-reply-form textarea`);
        if (form) form.focus();
      });
    },

    cancelReply() {
      this.replyTo = null;
      this.replyTargetId = null;
      this.replyToUser = null;
      this.replyBody = '';
    },

    async submit(parentId) {
      if (!this.commentsOpen) return;
      if (this.submitting) return;
      
      // Выбираем нужный стейт в зависимости от того, ответ это или корневой коммент
      const bodyText = parentId ? this.replyBody : this.newBody;
      
      if (!bodyText.trim() && !this.formImages.length) return;

      this.error = '';
      this.submitting = true;

      const fd = new FormData();
      fd.append('article_id', this.articleId);
      fd.append('body', bodyText); // <--- Отправляем правильный текст
      if (parentId) fd.append('parent_id', parentId);
      this.formImages.forEach(img => fd.append('image_ids[]', img.id));

      try {
        const r = await fetch('/api/comments', {
          method: 'POST',
          headers: { 'X-CSRF-Token': this.csrf() },
          body: fd,
        });
        const data = await r.json();
        if (!data.ok) throw new Error(data.error || 'Ошибка');

        const comment = { ...data.comment, images: data.comment.images || [] };

        if (parentId) {
          const root = this.comments.find(c => c.id === this.replyTo);
          if (root) {
            if (!root.replies) root.replies = [];
            root.replies.push(comment);
            root.repliesLoaded = true;
            root.repliesOpen = true;
            root.replyCount = (root.replyCount || 0) + 1;
            root.last_activity = comment.created_at;
            root.last_activity_ago = comment.time_ago;
          }
          this.replyBody = ''; // <--- Очищаем стейт ответа
          this.cancelReply();
        } else {
          this.comments.unshift({
            ...comment,
            replyCount: 0,
            last_activity: comment.created_at,
            last_activity_ago: comment.time_ago,
            replies: [],
            repliesOpen: false,
            repliesLoaded: false,
          });
          this.newBody = ''; // <--- Очищаем стейт корневого
          this.formImages = [];
          // Возвращаем редактор в режим "Написать"
          this.$nextTick(() => {
            if (window.resetMarkdownEditors) window.resetMarkdownEditors();
          });
        }

        if (data.server_time) this.lastSyncAt = data.server_time;

        // Обновляем состояние подписки и тост
        if (typeof data.subscribed !== 'undefined') {
          this.isSubscribed = !!data.subscribed;
        }
        if (data.just_subscribed) {
          this.showToast('Вы подписались на обсуждение');
        }

        this.$nextTick(() => this.highlightAll());
      } catch (e) {
        this.error = e.message;
      } finally {
        this.submitting = false;
      }
    },

    /* ─── Изображения в форме ──────────────────────── */

    onDrop(e) {
      this.dragOver = false;
      const files = Array.from(e.dataTransfer?.files || []).filter(f => f.type.startsWith('image/'));
      this.uploadFiles(files);
    },

    onPaste(e) {
      const items = Array.from(e.clipboardData?.items || []);
      const files = [];
      for (const it of items) {
        if (it.kind === 'file' && it.type.startsWith('image/')) {
          const f = it.getAsFile();
          if (f) files.push(f);
        }
      }
      if (files.length) this.uploadFiles(files);
    },

    onPick(e) {
      const files = Array.from(e.target.files || []);
      this.uploadFiles(files);
      e.target.value = '';
    },
	
	onDropEdit(e) {
      this.dragOver = false;
      const files = Array.from(e.dataTransfer?.files || []).filter(f => f.type.startsWith('image/'));
      this.uploadFiles(files, this.editingNewImages);
    },

    onPasteEdit(e) {
      const items = Array.from(e.clipboardData?.items || []);
      const files = [];
      for (const it of items) {
        if (it.kind === 'file' && it.type.startsWith('image/')) {
          const f = it.getAsFile();
          if (f) files.push(f);
        }
      }
      if (files.length) this.uploadFiles(files, this.editingNewImages);
    },

    onPickEdit(e) {
      const files = Array.from(e.target.files || []);
      this.uploadFiles(files, this.editingNewImages);
      e.target.value = '';
    },

    async uploadFiles(files, target = null) {
      if (!files.length) return;
      if (!this.isLoggedIn) return;

      // Куда складывать: formImages по умолчанию, либо переданный массив
      const bucket = target || this.formImages;
      const total  = (target ? (this.editingImages.length + this.editingNewImages.length) : this.formImages.length);
      const room   = 10 - total;

      if (room <= 0) {
        alert('Можно приложить не более 10 изображений.');
        return;
      }

      const slice = files.slice(0, room);
      this.uploading = true;

      for (const file of slice) {
        if (!file.type.startsWith('image/')) continue;
        if (file.size > 5 * 1024 * 1024) {
          alert(`«${file.name}» больше 5 МБ.`);
          continue;
        }
        try {
          const fd = new FormData();
          fd.append('file', file);
          const r = await fetch('/api/upload', {
            method: 'POST',
            headers: { 'X-CSRF-Token': this.csrf() },
            body: fd,
          });
          const data = await r.json();
          if (!data.ok) throw new Error(data.error || 'Ошибка загрузки');
          bucket.push(data.image);
        } catch (err) {
          alert(err.message);
        }
      }

      this.uploading = false;
    },

    removeFormImage(i) {
      this.formImages.splice(i, 1);
    },

    /* ─── Лайтбокс ─────────────────────────────────── */

    openLightbox(images, index = 0) {
      this.lightbox.images = images;
      this.lightbox.index = index;
      this.lightbox.open = true;
    },

    closeLightbox() {
      this.lightbox.open = false;
      this.lightbox.images = [];
      this.lightbox.index = 0;
    },

    nextLightbox() {
      if (!this.lightbox.images.length) return;
      this.lightbox.index = (this.lightbox.index + 1) % this.lightbox.images.length;
    },

    prevLightbox() {
      if (!this.lightbox.images.length) return;
      this.lightbox.index = (this.lightbox.index - 1 + this.lightbox.images.length) % this.lightbox.images.length;
    },

    /* ─── Контекстное меню ─────────────────────────── */

    openMenu(event, comment, root = null) {
      if (this.menu.open && this.menu.comment && this.menu.comment.id === comment.id) {
        this.closeMenu();
        return;
      }
      if (comment.is_deleted) return;
      event.stopPropagation();

      const target = event.currentTarget;
      if (!target) return;
      const btnRect = target.getBoundingClientRect();

      this.menu.open = true;
      this.menu.comment = comment;
      this.menu.root = root;
      this.menu.isOwn = this.currentUser === comment.username;
      this.menu.x = Math.max(8, btnRect.right - 220);
      this.menu.y = btnRect.bottom + 6;

      this.$nextTick(() => {
        const el = document.querySelector('.context-menu');
        if (!el) return;

        const menuRect = el.getBoundingClientRect();
        let x = btnRect.right - menuRect.width;
        let y = btnRect.bottom + 6;

        if (x < 8) x = 8;
        if (x + menuRect.width > window.innerWidth - 8) {
          x = window.innerWidth - menuRect.width - 8;
        }
        if (y + menuRect.height > window.innerHeight - 8) {
          y = btnRect.top - menuRect.height - 6;
          if (y < 8) y = 8;
        }

        this.menu.x = x;
        this.menu.y = y;

        window.addEventListener('mousedown', this._menuOutside = (e) => {
          if (!e.target.closest('.context-menu') && !e.target.closest('.action-menu')) {
            this.closeMenu();
          }
        }, { once: true });
      });
    },

    closeMenu() {
      this.menu.open = false;
      if (this._menuOutside) {
        window.removeEventListener('mousedown', this._menuOutside);
        this._menuOutside = null;
      }
    },
	
	menuOpenProfile() {
      const c = this.menu.comment;
      if (!c) { this.closeMenu(); return; }
      const url = '/u/' + encodeURIComponent(c.username);
      this.closeMenu();
      window.location.href = url;
    },

    menuReply() {
      const c = this.menu.comment;
      const root = this.menu.root;
      if (!c) { this.closeMenu(); return; }
      if (root) {
        if (!root.repliesOpen) this.toggleReplies(root);
        this.startReply(root, c);
      } else {
        this.startReply(c);
      }
      this.closeMenu();
    },

    menuQuote() {
      const c = this.menu.comment;
      if (!c) { this.closeMenu(); return; }
      
      // Берем сохраненный текст кнопки, так как при клике на меню выделение сбрасывается
      let sel = (this.quoteBtn.text || '').trim();
      if (!sel) {
        sel = (window.getSelection()?.toString() || '').trim();
      }
      if (!sel) { this.closeMenu(); return; }

      const root = this.menu.root;
      const quoted = sel.split('\n').map(l => '> ' + l).join('\n') + '\n\n';

      if (root && root.id !== c.id) {
        // Это ответ
        if (!root.repliesOpen) this.toggleReplies(root);
        this.replyTo = root.id;
        this.replyTargetId = c.id;
        this.replyToUser = c.username;
        
        if (!this.replyBody.includes('@' + c.username)) {
          this.replyBody = this.replyBody + '@' + c.username + ', \n\n' + quoted;
        } else {
          this.replyBody = this.replyBody + quoted;
        }
      } else {
        // Корневой
        this.replyTo = c.id;
        this.replyTargetId = c.id;
        this.replyToUser = c.username;
        this.replyBody = this.replyBody + quoted;
      }

      this.$nextTick(() => {
        if (window.initMarkdownEditors) window.initMarkdownEditors(document);
        const form = document.querySelector('.comment-reply-form textarea');
        if (form) {
          form.focus();
          form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });

      this.closeMenu();
      window.getSelection().removeAllRanges();
    },

    async menuCopyLink() {
      const c = this.menu.comment;
      if (!c) { this.closeMenu(); return; }
      const url = location.origin + location.pathname + '#c-' + c.id;
      try {
        await navigator.clipboard.writeText(url);
      } catch {
        prompt('Скопируйте ссылку:', url);
      }
      this.closeMenu();
    },

    menuEdit() {
      const c = this.menu.comment;
      if (!c) { this.closeMenu(); return; }
      this.editingId = c.id;
      this.editingBody = c.body_raw || '';
      this.editingImages = (c.images || []).slice();
      this.editingNewImages = [];
      this.closeMenu();
      
      this.$nextTick(() => {
        // Инициализируем Markdown-редактор для только что появившегося textarea
        if (window.initMarkdownEditors) window.initMarkdownEditors(document);
        
        const ta = document.querySelector('.comment-edit textarea');
        if (ta) ta.focus();
      });
    },

    extractText(html) {
      const tmp = document.createElement('div');
      tmp.innerHTML = html;
      return tmp.textContent || '';
    },

    cancelEdit() {
      this.editingId = null;
      this.editingBody = '';
      this.editingImages = [];
      this.editingNewImages = [];
    },

    async saveEdit(comment) {
      if (!this.editingBody.trim()) return;
      const fd = new FormData();
      fd.append('body', this.editingBody);
      (this.editingImages || []).forEach(img => fd.append('keep_image_ids[]', img.id));
      (this.editingNewImages || []).forEach(img => fd.append('new_image_ids[]', img.id));
      try {
        const r = await fetch(`/api/comments/${comment.id}/update`, {
          method: 'POST',
          headers: { 'X-CSRF-Token': this.csrf() },
          body: fd,
        });
        const data = await r.json();
        if (!data.ok) throw new Error(data.error || 'Ошибка');
        if (data.server_time) this.lastSyncAt = data.server_time;
        this.cancelEdit();
        await this.reloadAll();
      } catch (e) {
        alert(e.message);
      }
    },

    async menuDelete() {
      const c = this.menu.comment;
      if (!c) { this.closeMenu(); return; }
      if (!confirm('Удалить комментарий?')) { this.closeMenu(); return; }

      const fd = new FormData();
      try {
        const r = await fetch(`/api/comments/${c.id}/delete`, {
          method: 'POST',
          headers: { 'X-CSRF-Token': this.csrf() },
          body: fd,
        });
        const data = await r.json();
        if (!data.ok) throw new Error(data.error || 'Ошибка');
        if (data.server_time) this.lastSyncAt = data.server_time;
        await this.reloadAll();
      } catch (e) {
        alert(e.message);
      }
      this.closeMenu();
    },

    async menuHide() {
      const c = this.menu.comment;
      if (!c) { this.closeMenu(); return; }
      if (!confirm('Скрыть комментарий?')) { this.closeMenu(); return; }
      await this.moderationPost(`/api/comments/${c.id}/hide`);
      this.closeMenu();
    },

    async menuUnhide() {
      const c = this.menu.comment;
      if (!c) { this.closeMenu(); return; }
      await this.moderationPost(`/api/comments/${c.id}/unhide`);
      this.closeMenu();
    },

    async menuPin() {
      const c = this.menu.comment;
      if (!c) { this.closeMenu(); return; }
      await this.moderationPost(`/api/comments/${c.id}/pin`);
      this.closeMenu();
    },

    async menuUnpin() {
      const c = this.menu.comment;
      if (!c) { this.closeMenu(); return; }
      await this.moderationPost(`/api/comments/${c.id}/unpin`);
      this.closeMenu();
    },

    async menuRestrict() {
      const c = this.menu.comment;
      if (!c) { this.closeMenu(); return; }
      if (!confirm(`Ограничить ${c.username} в этой статье?`)) { this.closeMenu(); return; }

      const fd = new FormData();
      fd.append('user_id', c.user_id);
      try {
        const r = await fetch(`/api/articles/${this.articleId}/restrict-user`, {
          method: 'POST',
          headers: { 'X-CSRF-Token': this.csrf() },
          body: fd,
        });
        const data = await r.json();
        if (!data.ok) throw new Error(data.error || 'Ошибка');
        alert(`${c.username} больше не может комментировать эту статью.`);
      } catch (e) {
        alert(e.message);
      }
      this.closeMenu();
    },

    async moderationPost(url) {
      const fd = new FormData();
      try {
        const r = await fetch(url, {
          method: 'POST',
          headers: { 'X-CSRF-Token': this.csrf() },
          body: fd,
        });
        const data = await r.json();
        if (!data.ok) throw new Error(data.error || 'Ошибка');
        if (data.server_time) this.lastSyncAt = data.server_time;
        await this.reloadAll();
      } catch (e) {
        alert(e.message);
      }
    },

    async reloadAll() {
      const openIds = this.comments
        .filter(c => c.repliesOpen && c.repliesLoaded)
        .map(c => c.id);

      await this.load();

      for (const id of openIds) {
        const c = this.comments.find(x => x.id === id);
        if (c) await this.toggleReplies(c);
      }
      this.$nextTick(() => this.highlightAll());
    },

    async handleHash() {
      const m = location.hash.match(/^#c-(\d+)$/);
      if (!m) return;
      const id = parseInt(m[1], 10);

      // Дать Alpine отрисовать список
      await this.$nextTick();

      const root = this.comments.find(c => c.id === id);
      if (root) {
        this.scrollToAndHighlight(id);
        return;
      }

      try {
        const r = await fetch(`/api/comments/${id}`);
        const data = await r.json();
        if (!data.ok) return;
        const parentRoot = this.comments.find(c => c.id === data.comment.root_id);
        if (!parentRoot) return;
        if (!parentRoot.repliesOpen) await this.toggleReplies(parentRoot);
        this.$nextTick(() => this.scrollToAndHighlight(id));
      } catch {}
    },
	
	async markOutlineRead(id) {
      const c = this.comments.find(x => x.id === id);
      if (c) {
        const ts = this.serverTimeToMs(c.last_activity || c.created_at);
        if (ts > this.lastSeen) {
          this.lastSeen = ts;
          // Отправляем на сервер, чтобы при перезагрузке бейдж не вернулся
          try {
            const fd = new FormData();
            fd.append('until_comment_id', id);
            await fetch(`/api/articles/${this.articleId}/mark-read`, {
              method: 'POST',
              headers: { 'X-CSRF-Token': this.csrf() },
              body: fd,
            });
          } catch {}
        }
      }
    },

    scrollToAndHighlight(id) {
      const el = document.getElementById('c-' + id);
      if (!el) return;
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      el.classList.add('comment-highlight');
      setTimeout(() => el.classList.remove('comment-highlight'), 8000);
    },
  }));
});