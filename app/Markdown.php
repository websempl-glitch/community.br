<?php
declare(strict_types=1);

namespace App;

class Markdown
{
    public static function toHtml(string $md): string
    {
        $md = str_replace(["\r\n", "\r"], "\n", $md);
        $md = trim($md);
        $md = htmlspecialchars($md, ENT_QUOTES, 'UTF-8');

        $blocks = [];
        return self::renderEscaped($md, $blocks);
    }

    private static function renderEscaped(string $md, array &$blocks): string
    {
        // 1. Блоки кода ```
        $md = preg_replace_callback(
            '#```(\w*)\n(.*?)```#s',
            function ($m) use (&$blocks) {
                $idx = count($blocks);
                $lang = $m[1] !== '' ? $m[1] : 'plaintext';
                $langLabel = $m[1] !== '' ? e($m[1]) : 'код';

                $blocks[$idx] = '<div class="code-wrap">'
                    .   '<div class="code-head">'
                    .     '<span class="code-lang">' . $langLabel . '</span>'
                    .     '<button type="button" class="code-copy" data-code-copy>Копировать</button>'
                    .   '</div>'
                    .   '<pre class="code-block"><code class="language-' . e($lang) . '">'
                    .     rtrim($m[2], "\n")
                    .   '</code></pre>'
                    . '</div>';
                return "\n\n\x1FBLOCK{$idx}\x1F\n\n";
            },
            $md
        );

        // 2. Спойлеры
        $md = preg_replace_callback(
            '#\[!spoiler\](.*?)\[/spoiler\]#s',
            function ($m) use (&$blocks) {
                $idx = count($blocks);
                $inner = trim($m[1]);
                $blocks[$idx] = '<details class="spoiler">'
                    .   '<summary>Спойлер</summary>'
                    .   '<div class="spoiler-content">'
                    .     self::renderEscaped($inner, $blocks)
                    .   '</div>'
                    . '</details>';
                return "\n\n\x1FBLOCK{$idx}\x1F\n\n";
            },
            $md
        );

        // 2.5 Заменяем 3 и более переносов (с пробелами) на маркеры пустых строк
        $md = preg_replace_callback('/\n(?:[ \t]*\n){2,}/', function($m) {
            $n = substr_count($m[0], "\n") - 2;
            return "\n\n\x1FEMPTY{$n}\x1F\n\n";
        }, $md);

        // 3. Якоря <anchor>текст</anchor>
        $md = preg_replace('/&lt;anchor&gt;(.*?)&lt;\/anchor&gt;/', '<span id="$1" class="anchor-target"></span>', $md);
		
		// 3.5. Выравнивание текста
        $md = preg_replace('/\[left\](.*?)\[\/left\]/s', '<div style="text-align: left;">$1</div>', $md);
        $md = preg_replace('/\[center\](.*?)\[\/center\]/s', '<div style="text-align: center;">$1</div>', $md);
        $md = preg_replace('/\[right\](.*?)\[\/right\]/s', '<div style="text-align: right;">$1</div>', $md);

        // 4. Цитаты (рекурсивно)
        $md = preg_replace_callback(
            '/^(?:&gt;.*(?:\n|$))+/m',
            function ($m) use (&$blocks) {
                $block = rtrim($m[0], "\n");
                $lines = explode("\n", $block);
                $inner = implode("\n", array_map(function ($l) {
                    return preg_replace('/^&gt; ?/', '', $l);
                }, $lines));
                $innerHtml = self::renderEscaped($inner, $blocks);
                return "\n\n<blockquote>" . $innerHtml . "</blockquote>\n\n";
            },
            $md
        );

        // 5. Списки (Маркированные и Нумерованные)
        $md = preg_replace_callback('/^(?:[ \t]*[-*][ \t]+.+(?:\n|$))+/m', function ($m) {
            $lines = explode("\n", trim($m[0]));
            $html = "<ul>\n";
            foreach ($lines as $line) {
                if (trim($line) === '') continue;
                $line = preg_replace('/^[ \t]*[-*][ \t]+/', '', $line);
                $html .= "<li>" . trim($line) . "</li>\n";
            }
            $html .= "</ul>";
            return "\n\n" . $html . "\n\n";
        }, $md);

        $md = preg_replace_callback('/^(?:[ \t]*\d+\.[ \t]+.+(?:\n|$))+/m', function ($m) {
            $lines = explode("\n", trim($m[0]));
            $html = "<ol>\n";
            foreach ($lines as $line) {
                if (trim($line) === '') continue;
                $line = preg_replace('/^[ \t]*\d+\.[ \t]+/', '', $line);
                $html .= "<li>" . trim($line) . "</li>\n";
            }
            $html .= "</ol>";
            return "\n\n" . $html . "\n\n";
        }, $md);

        // 6. Заголовки (добавляем переносы вокруг)
        $md = preg_replace('/^(#{1,6} .+)$/m', "\n$1\n", $md);
        
        // 7. Инлайн-код
        $md = preg_replace('#`([^`\n]+)`#', '<code>$1</code>', $md);

        // 8. Заголовки
        $md = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $md);
        $md = preg_replace('/^## (.+)$/m',  '<h2>$1</h2>', $md);
        $md = preg_replace('/^# (.+)$/m',   '<h1>$1</h1>', $md);

        // 9. Форматирование текста
        $md = preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $md);
        $md = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $md);
        $md = preg_replace('/__([^_\n]+)__/', '<u>$1</u>', $md);
        $md = preg_replace('/~~([^~\n]+)~~/', '<s>$1</s>', $md);

        // 10. Картинки
        $md = preg_replace_callback(
            '/!\[([^\]]*)\]\((https?:\/\/[^\s)]+)\)(?:\{([^}]+)\})?/',
            function ($m) {
                $alt = $m[1];
                $url = $m[2];
                $params = $m[3] ?? '';
                $style = '';
                $class = 'md-image';
                
                if ($params) {
                    if (preg_match('/width=([^ \}]+)/', $params, $w)) {
                        $wVal = $w[1];
                        $style .= 'width:' . (is_numeric($wVal) ? $wVal . 'px' : $wVal) . ';';
                    }
                    if (preg_match('/align=(left|right|center)/', $params, $a)) {
                        $align = $a[1];
                        if ($align === 'center') {
                            $style .= 'display:block; margin:0 auto;';
                        } else {
                            $style .= 'float:' . $align . ';';
                            $class .= ' md-image-' . $align;
                        }
                    }
                }
                return '<img src="' . $url . '" alt="' . $alt . '" class="' . $class . '" style="' . $style . '">';
            },
            $md
        );

        // 11. Ссылки (включая якоря #)
        $md = preg_replace_callback(
            '/\[([^\]]+)\]\(((?:https?:\/\/|#)[^\s)]+)\)/',
            function ($m) {
                $href = $m[2];
                $target = str_starts_with($href, '#') ? '' : ' target="_blank" rel="noopener"';
                return '<a href="' . $href . '"' . $target . '>' . $m[1] . '</a>';
            },
            $md
        );

        // Возвращаем блоки
        foreach ($blocks as $i => $html) {
            $md = str_replace("\x1FBLOCK{$i}\x1F", $html, $md);
        }

        // 12. Разбиваем на абзацы
        $md = str_replace("\r", "", $md);
        $parts = preg_split('/(\n{2,})/', trim($md), -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = [];

        foreach ($parts as $i => $block) {
            // Нечетные индексы — это разделители, просто пропускаем их
            if ($i % 2 === 1) {
                continue;
            }

            $block = trim($block);
            if ($block === '') continue;

            // Обработка наших маркеров пустых строк
            if (preg_match('/^\x1FEMPTY(\d+)\x1F$/', $block, $m)) {
                $count = (int)$m[1];
                $prev = end($out);
                $next = trim($parts[$i + 2] ?? '');

                $prevIsBlock = $prev !== false && preg_match('#^<(h[1-6]|pre|blockquote|ul|ol|div|details|img)#', $prev);
                $nextIsBlock = $next !== '' && preg_match('#^<(h[1-6]|pre|blockquote|ul|ol|div|details|img)#', $next);

                if (!$prevIsBlock && !$nextIsBlock) {
                    $out[] = str_repeat('<div class="md-extra-line"></div>', $count);
                }
            } elseif (preg_match('#^<(h[1-6]|pre|blockquote|ul|ol|div|details|img)#', $block)) {
                $out[] = $block;
            } else {
                // Безопасно заменяем переносы на <br>, не трогая теги
                $out[] = '<p>' . str_replace("\n", '<br>', $block) . '</p>';
            }
        }

        return implode("\n", $out);
    }
}