<?php
return [
    'types' => [
        'performance' => [
            'line' => ['responsive' => true, 'animation' => true],
            'bar' => ['stacked' => true],
            'radar' => ['fill' => true],
            'scatter' => ['regression' => true]
        ],
        'resource' => [
            'heatmap' => ['scale' => ['min' => 0, 'max' => 100]],
            'gauge' => ['responsive' => true],
            'matrix' => ['colorScale' => 'diverging']
        ],
        'prediction' => [
            'area' => ['stacked' => true],
            'bubble' => ['scale' => 'linear'],
            'candlestick' => ['upColor' => 'green', 'downColor' => 'red']
        ]
    ],
    'defaults' => [
        'responsive' => true,
        'maintainAspectRatio' => false,
        'animation' => ['duration' => 1000],
        'plugins' => [
            'legend' => ['position' => 'top'],
            'tooltip' => ['mode' => 'index']
        ]
    ]
];
