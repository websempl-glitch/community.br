document.addEventListener('alpine:init', () => {
  Alpine.data('notificationsApp', () => ({
    unreadCount: 0,
    isOpen: false,
    items: [],
    loading: false,
    pollTimer: null,

    init() {
      this.checkCount();
      this.pollTimer = setInterval(() => {
        if (document.visibilityState === 'visible') this.checkCount();
      }, 30000);
    },

    async checkCount() {
      try {
        const r = await fetch('/api/notifications/count');
        const data = await r.json();
        if (data.ok) this.unreadCount = data.count;
      } catch (e) {}
    },

    async toggle() {
      this.isOpen = !this.isOpen;
      if (this.isOpen) await this.loadList();
    },

    async loadList() {
      this.loading = true;
      try {
        const r = await fetch('/api/notifications');
        const data = await r.json();
        if (data.ok) {
          this.items = data.notifications;
          // НЕ помечаем прочитанными — пользователь должен кликнуть сам
        }
      } finally {
        this.loading = false;
      }
    },

    async markAllRead() {
      const csrf = document.querySelector('meta[name=csrf-token]').content;
      await fetch('/api/notifications/read-all', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrf }
      });
      this.unreadCount = 0;
      this.items.forEach(i => i.is_read = true);
    },

    async goTo(item, event) {
      event.preventDefault();
      if (!item.is_read) {
        const csrf = document.querySelector('meta[name=csrf-token]').content;
        try {
          // Ждем, пока сервер отметит уведомление как прочитанное
          await fetch(`/api/notifications/${item.id}/read`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf }
          });
          this.unreadCount = Math.max(0, this.unreadCount - 1);
          item.is_read = true;
        } catch (e) {}
      }
      window.location.href = item.url;
    },

    close() {
      this.isOpen = false;
    }
  }));
});