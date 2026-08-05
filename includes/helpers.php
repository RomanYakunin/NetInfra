<?php

/**
 * Возвращает HTML-тег <img> для иконки
 *
 * @param string $path   Путь к файлу
 * @param array  $options Ассоциативный массив:
 *                       'alt'    => string,
 *                       'class'  => string,
 *                       'style'  => string,
 *                       'width'  => int|string,
 *                       'height' => int|string,
 *                       'id'     => string,
 *                       'title'  => string,
 *                       'size'   => 'default'|'small'|'large'
 * @return string
 */
function icon($path, $options = [])
{
    // Предопределённые размеры
    $sizeMap = [
    'default' => ['width' => 24, 'height' => 24],
    'small'   => ['width' => 16, 'height' => 16],
    'large'   => ['width' => 48, 'height' => 48],
    'medium'  => ['width' => 45, 'height' => 45],   // <-- новый размер
];

    if (isset($options['size']) && isset($sizeMap[$options['size']])) {
        $defaultSize = $sizeMap[$options['size']];
        $options['width']  = $options['width']  ?? $defaultSize['width'];
        $options['height'] = $options['height'] ?? $defaultSize['height'];
        unset($options['size']);
    }

    $defaults = [
        'alt'    => '',
        'class'  => '',
        'style'  => '',
        'width'  => 24,
        'height' => 24,
        'id'     => '',
        'title'  => '',
    ];
    $opts = array_merge($defaults, $options);

    $attrs = [];
    $attrs[] = 'src="' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '"';
    if ($opts['alt'] !== '') {
        $attrs[] = 'alt="' . htmlspecialchars($opts['alt'], ENT_QUOTES, 'UTF-8') . '"';
    }
    if ($opts['class']) {
        $attrs[] = 'class="' . htmlspecialchars($opts['class'], ENT_QUOTES, 'UTF-8') . '"';
    }
    if ($opts['style']) {
        $attrs[] = 'style="' . htmlspecialchars($opts['style'], ENT_QUOTES, 'UTF-8') . '"';
    }
    if ($opts['width']) {
        $attrs[] = 'width="' . htmlspecialchars((string)$opts['width'], ENT_QUOTES, 'UTF-8') . '"';
    }
    if ($opts['height']) {
        $attrs[] = 'height="' . htmlspecialchars((string)$opts['height'], ENT_QUOTES, 'UTF-8') . '"';
    }
    if ($opts['id']) {
        $attrs[] = 'id="' . htmlspecialchars($opts['id'], ENT_QUOTES, 'UTF-8') . '"';
    }
    if ($opts['title']) {
        $attrs[] = 'title="' . htmlspecialchars($opts['title'], ENT_QUOTES, 'UTF-8') . '"';
    }

    return '<img ' . implode(' ', $attrs) . '>';
}