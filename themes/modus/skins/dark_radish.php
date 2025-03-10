<?php

/*gradients facebook '#3B5998','#2B4170' ; Google '#E64522','#C33219' ; Pinterest '#CB2027','#A0171C'; Turquoise: '#009CDA','#0073B2'*/
$skin = [
    'BODY' => [
        // REQUIRED
        'backgroundColor' => '#141414',
        // REQUIRED
        'color' => '#bbb',
    ],

    'A' => [
        // REQUIRED
        'color' => '#ddd',
    ],

    'A:hover' => [
        'color' => '#fff',
    ],

    'controls' => [
        'backgroundColor' => 'transparent',
        'color' => 'inherit',
        'border' => '1px solid gray',
    ],

    'controls:focus' => [
        'backgroundColor' => '#3F3F3F',
        'color' => '#fff',
        'boxShadow' => '0 0 2px white',
    ],

    'buttons' => [
        'backgroundColor' => '#A0171C',
        'gradient' => ['#CB2027', '#A0171C'],
        'color' => '#ddd',
        'border' => '1px solid gray',
    ],

    'buttonsHover' => [
        'color' => '#fff',
        'boxShadow' => '0 0 2px white',
    ],

    'menubar' => [
        'backgroundColor' => '#A0171C',
        'gradient' => ['#CB2027', '#A0171C'],
        'color' => '#ddd',
        'link' => [
            'color' => '#fff',
        ],
        //'linkHover'					=> array( 'color' => '#fff' ),
    ],

    'dropdowns' => [
        // REQUIRED - cannot be transparent
        'backgroundColor' => '#3F3F3F',
    ],

    'pageTitle' => [
        'backgroundColor' => '#CB2027',
        'gradient' => ['#A0171C', '#CB2027'],
        'color' => '#ddd',
        'link' => [
            'color' => '#fff',
        ],
        //'linkHover'					=> array( 'color' => '#fff' ),
        'textShadowColor' => '#000',
    ],

    /*'pictureBar' => array(
            'backgroundColor' 	=> '#3F3F3F',
        ),*/

    'widePictureBar' => [
        'backgroundColor' => '#3F3F3F',
    ],

    'pictureWideInfoTable' => [
        'backgroundColor' => '#3F3F3F',
    ],

    'comment' => [
        'backgroundColor' => '#3F3F3F',
    ],

    // should be white or around white
    /*'albumLegend' => array(
            'color' 	=> '#fff',
        ),*/
];
