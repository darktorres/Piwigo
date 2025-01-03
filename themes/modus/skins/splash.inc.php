<?php

declare(strict_types=1);

/*gradients facebook '#3B5998','#2B4170' ; Google '#E64522','#C33219' ; Pinterest '#CB2027','#A0171C'*/
$themeconf['colorscheme'] = 'clear';

$skin = [
    'BODY' => [
        // REQUIRED
        'backgroundColor' => '#fff',
        // REQUIRED
        'color' => '#000',
    ],

    'A' => [
        // REQUIRED
        'color' => '#00f',
    ],

    'A:hover' => [
        'color' => '#000',
    ],

    'menubar' => [
        'backgroundColor' => '#C33219',
        'gradient' => ['#E64522', '#C33219'],
        'color' => '#bbb',
        'link' => [
            'color' => '#ddd',
        ],
        'linkHover' => [
            'color' => '#fff',
        ],
    ],

    'dropdowns' => [
        // REQUIRED - cannot be transparent
        'backgroundColor' => '#f2f2f2',
    ],

    'pageTitle' => [
        'backgroundColor' => '#2B4170',
        'gradient' => ['#3B5998', '#2B4170'],
        'color' => '#bbb',
        'link' => [
            'color' => '#ddd',
        ],
        'linkHover' => [
            'color' => '#fff',
        ],
    ],

    'pictureBar' => [
        'backgroundColor' => '#ccc',
    ],

    'widePictureBar' => [
        'backgroundColor' => '#dca',
    ],

    'pictureWideInfoTable' => [
        'backgroundColor' => '#ccc',
    ],

    'comment' => [
        'backgroundColor' => '#ccc',
    ],

    // should be white or around white
    'albumLegend' => [
        'color' => '#fff',
    ],
];
