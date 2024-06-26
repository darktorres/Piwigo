<?php

declare(strict_types=1);

$themeconf['colorscheme'] = 'clear';

/*gradients facebook '#3B5998','#2B4170' ; Google '#E64522','#C33219' ; Pinterest '#CB2027','#A0171C'; Turquoise: '#009CDA','#0073B2'*/
$skin = [
    'BODY' => [
        // REQUIRED
        'backgroundColor' => '#eee',
        // REQUIRED
        'color' => '#444',
    ],

    'A' => [
        // REQUIRED
        'color' => '#222',
    ],

    'A:hover' => [
        'color' => '#000',
    ],

    'menubar' => [
        'backgroundColor' => '#ccc',
        //'gradient' 					=> array('#3B5998','#2B4170'),
        'color' => '#444',
        'link' => [
            'color' => '#222',
        ],
        'linkHover' => [
            'color' => '#000',
        ],
    ],

    'dropdowns' => [
        // REQUIRED - cannot be transparent
        'backgroundColor' => '#ccc',
    ],

    'pageTitle' => [
        'backgroundColor' => '#ccc',
        //'gradient' 					=> array('#A0171C','#CB2027'),
        'color' => '#444',
        'link' => [
            'color' => '#222',
        ],
        'linkHover' => [
            'color' => '#000',
        ],
    ],

    'pictureBar' => [
        'backgroundColor' => '#ccc',
    ],

    /*'widePictureBar' => array(
            'backgroundColor' 	=> '#ccc',
        ),*/

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
