(function () {
  'use strict';

    const BTN = {
    h1:        { icon: '<span style="font-weight:700;font-size:13px;">H1</span>', title: 'Заголовок 1', line: '# ' },
    h2:        { icon: '<span style="font-weight:700;font-size:13px;">H2</span>', title: 'Заголовок 2', line: '## ' },
    h3:        { icon: '<span style="font-weight:700;font-size:13px;">H3</span>', title: 'Заголовок 3', line: '### ' },
    bold:      { icon: '<b>B</b>', title: 'Жирный (Ctrl+B)', wrap: ['**', '**'], placeholder: 'жирный текст' },
    italic:    { icon: '<i>I</i>', title: 'Курсив (Ctrl+I)', wrap: ['*', '*'], placeholder: 'курсив' },
    underline: { icon: '<u>U</u>', title: 'Подчёркнутый', wrap: ['__', '__'], placeholder: 'подчёркнутый' },
    strike:    { icon: '<s>S</s>', title: 'Зачёркнутый', wrap: ['~~', '~~'], placeholder: 'зачёркнутый' },
	
	align_left:   { icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="17" y1="10" x2="3" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="17" y1="18" x2="3" y2="18"></line></svg>', title: 'По левому краю', wrap: ['[left]', '[/left]'], placeholder: 'текст' },
    align_center: { icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="10" x2="6" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="18" y1="18" x2="6" y2="18"></line></svg>', title: 'По центру', wrap: ['[center]', '[/center]'], placeholder: 'текст' },
    align_right:  { icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="21" y1="10" x2="7" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="7" y2="18"></line></svg>', title: 'По правому краю', wrap: ['[right]', '[/right]'], placeholder: 'текст' },
    
    link:      { icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>', title: 'Ссылка', wrap: ['[', '](url)'], placeholder: 'текст ссылки' },
    code:      { icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>', title: 'Инлайн-код', wrap: ['`', '`'], placeholder: 'код' },
    codeblock: { icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><polyline points="9 9 7 12 9 15"></polyline><polyline points="15 9 17 12 15 15"></polyline></svg>', title: 'Блок кода', block: '```php\n', blockEnd: '\n```' },
    quote:     { icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 17h3l2-4V7H5v6h3zM16 17h3l2-4V7h-6v6h3z"/></svg>', title: 'Цитата', line: '> ' },
    spoiler:   { icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>', title: 'Спойлер', wrap: ['[!spoiler]', '[/spoiler]'], placeholder: 'скрытый текст' },
    image:     { icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>', title: 'Картинка', wrap: ['![', '](https://example.com/image.jpg)'] },
    anchor:    { icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="3"></circle><line x1="12" y1="22" x2="12" y2="8"></line><path d="M5 12H2a10 10 0 0 0 20 0h-3"></path></svg>', title: 'Якорь', wrap: ['<anchor>', '</anchor>'], placeholder: 'название-якоря' },
    list:      { icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>', title: 'Маркированный список', line: '- ' },
    ordered:   { icon: '<span style="font-weight:700;font-size:13px;">1.</span>', title: 'Нумерованный список', line: '1. ' },
    
    help:      { icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>', title: 'Помощь по форматированию', link: '/help/markdown' },
  };

  // Полный набор кнопок для всех редакторов
  const ALL_BUTTONS = [
    'h1', 'h2', 'h3', 'bold', 'italic', 'underline', 'strike',
	'align_left', 'align_center', 'align_right',
    'link', 'code', 'codeblock', 'quote', 'spoiler', 
    'image', 'anchor', 'list', 'ordered', 'help'
  ];

  function setSelection(textarea, start, end) {
    textarea.focus();
    textarea.setSelectionRange(start, end);
    // Принудительно ставим каретку на место после того, как Alpine обновит DOM
    setTimeout(() => {
      textarea.setSelectionRange(start, end);
    }, 10);
  }

  function applyWrap(textarea, before, after, placeholder) {
    const start = textarea.selectionStart;
    const end   = textarea.selectionEnd;
    const value = textarea.value;
    const selected = value.substring(start, end);

    let insert, newStart, newEnd;

    if (selected) {
      insert = before + selected + after;
      newStart = start + before.length;
      newEnd   = newStart + selected.length;
    } else {
      insert = before + placeholder + after;
      newStart = start + before.length;
      newEnd   = newStart + placeholder.length;
    }

    textarea.value = value.substring(0, start) + insert + value.substring(end);
    setSelection(textarea, newStart, newEnd);
    notify(textarea);
  }

  function applyLine(textarea, prefix) {
    const start = textarea.selectionStart;
    const end   = textarea.selectionEnd;
    const value = textarea.value;

    let lineStart = value.lastIndexOf('\n', start - 1) + 1;
    let lineEnd   = value.indexOf('\n', end);
    if (lineEnd === -1) lineEnd = value.length;

    const block = value.substring(lineStart, lineEnd);
    const lines = block.split('\n').map(l => prefix + l).join('\n');

    textarea.value = value.substring(0, lineStart) + lines + value.substring(lineEnd);
    setSelection(textarea, lineStart, lineStart + lines.length);
    notify(textarea);
  }

  function applyBlock(textarea, open, close) {
    const start = textarea.selectionStart;
    const end   = textarea.selectionEnd;
    const value = textarea.value;
    const selected = value.substring(start, end);

    const before = (start > 0 && value[start - 1] !== '\n') ? '\n\n' : '';
    const after  = (end < value.length && value[end] !== '\n') ? '\n\n' : '';

    const inner = selected || 'код';
    const insert = before + open + inner + close + after;

    textarea.value = value.substring(0, start) + insert + value.substring(end);

    const cursorStart = start + before.length + open.length;
    const cursorEnd   = cursorStart + inner.length;
    setSelection(textarea, cursorStart, cursorEnd);
    notify(textarea);
  }

  function notify(textarea) {
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function handleKeydown(e, action) {
    const isMac = navigator.platform.toUpperCase().includes('MAC');
    const mod = isMac ? e.metaKey : e.ctrlKey;

    if (mod && !e.shiftKey && !e.altKey) {
      if (e.key === 'b' || e.key === 'B') { e.preventDefault(); action('bold'); return true; }
      if (e.key === 'i' || e.key === 'I') { e.preventDefault(); action('italic'); return true; }
    }
    return false;
  }

  // Функция авто-расширения textarea
  function initAutoResize(textarea) {
    const resize = () => {
      textarea.style.height = 'auto';
      let newHeight = textarea.scrollHeight;
      
      // Ограничиваем максимальную высоту 160px
      if (newHeight > 220) {
        textarea.style.height = '220px';
        textarea.style.overflowY = 'auto';
      } else {
        textarea.style.height = newHeight + 'px';
        textarea.style.overflowY = 'hidden';
      }
    };

    textarea.style.overflowY = 'hidden';
    textarea.style.resize = 'none'; // Отключаем ручное растягивание
    textarea.addEventListener('input', resize);
    resize(); // Первичный вызов для установки высоты
  }

  window.attachMarkdownEditor = function (textarea) {
    if (textarea.dataset.mdAttached === '1') return;
    textarea.dataset.mdAttached = '1';

    // Включаем авто-расширение для всех редакторов
    initAutoResize(textarea);

    const wrapper = document.createElement('div');
    wrapper.className = 'md-editor';

    const toolbar = document.createElement('div');
    toolbar.className = 'md-toolbar';

    let previewMode = false;
    let previewEl = null;

    function action(key) {
      const cfg = BTN[key];
      if (!cfg) return;

      if (cfg.wrap) applyWrap(textarea, cfg.wrap[0], cfg.wrap[1], cfg.placeholder || '');
      else if (cfg.line) applyLine(textarea, cfg.line);
      else if (cfg.block) applyBlock(textarea, cfg.block, cfg.blockEnd || '');
    }

    ALL_BUTTONS.forEach(key => {
      const cfg = BTN[key];
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'md-btn' + (key === 'help' ? ' md-btn-help' : '');
      btn.title = cfg.title;
      btn.innerHTML = cfg.icon;
      btn.addEventListener('click', () => {
        if (cfg.link) {
            window.open(cfg.link, '_blank');
            return;
        }
        if (previewMode) togglePreview();
        action(key);
      });
      toolbar.appendChild(btn);
    });

    textarea.parentNode.insertBefore(wrapper, textarea);
    wrapper.appendChild(toolbar);

    const tabs = document.createElement('div');
    tabs.className = 'md-tabs';
    tabs.innerHTML =
      '<button type="button" class="md-tab is-active" data-tab="write">Написать</button>' +
      '<button type="button" class="md-tab" data-tab="preview">Просмотр</button>';
    wrapper.appendChild(tabs);

    wrapper.appendChild(textarea);

    previewEl = document.createElement('div');
    previewEl.className = 'md-preview';
    previewEl.hidden = true;
    wrapper.appendChild(previewEl);

    const tabWrite   = tabs.querySelector('[data-tab="write"]');
    const tabPreview = tabs.querySelector('[data-tab="preview"]');

    async function loadPreview() {
      previewEl.innerHTML = '<p class="hint">Загрузка…</p>';
      try {
        const csrf = document.querySelector('meta[name=csrf-token]').content;
        const fd = new FormData();
        fd.append('text', textarea.value);
        const r = await fetch('/api/markdown/preview', {
          method: 'POST',
          headers: { 'X-CSRF-Token': csrf },
          body: fd,
        });
        const data = await r.json();
        if (!data.ok) throw new Error(data.error || 'Ошибка');
        previewEl.innerHTML = data.html || '<p class="hint">Пусто.</p>';
      } catch (e) {
        previewEl.innerHTML = '<p class="hint" style="color:var(--danger)">Не удалось получить превью</p>';
      }
    }

    function togglePreview() {
      previewMode = !previewMode;
      textarea.hidden = previewMode;
      previewEl.hidden = !previewMode;
      tabWrite.classList.toggle('is-active', !previewMode);
      tabPreview.classList.toggle('is-active', previewMode);
      if (previewMode) loadPreview();
    }

    tabWrite.addEventListener('click', () => { if (previewMode) togglePreview(); });
    tabPreview.addEventListener('click', () => { if (!previewMode) togglePreview(); });

    textarea.addEventListener('keydown', (e) => handleKeydown(e, action));
  };

  window.initMarkdownEditors = function (root) {
    (root || document).querySelectorAll('textarea[data-md-editor]').forEach(el => {
      window.attachMarkdownEditor(el);
    });
  };

  window.resetMarkdownEditors = function () {
    document.querySelectorAll('.md-editor').forEach(w => {
      const writeBtn = w.querySelector('.md-tab[data-tab="write"]');
      if (writeBtn && !writeBtn.classList.contains('is-active')) {
        writeBtn.click();
      }
    });
  };

  document.addEventListener('DOMContentLoaded', () => {
    window.initMarkdownEditors(document);
  });
})();