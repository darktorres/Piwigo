<?php

$lines = file('changelog.txt');
$show = false;
$first = true;

echo '<div class="changelog">';

foreach ($lines as $line_num => $line):
    if (trim($line) !== '' && trim($line) !== '0'):
        if ($show):
            if (str_starts_with($line, 'version')):
                if ($first):
                    $first = false;
                else:
                    echo '</ul>';
                endif;
            echo '<h3>' . htmlspecialchars(str_replace('version ', '', $line)) . "</h3>\n<ul>";
        else:
            echo '<li>' . htmlspecialchars($line) . "</li>\n";
        endif;
    elseif (trim($line) === '=== Changelog ==='):
        $show = true;
    endif;
    endif;
endforeach;
echo '</ul></div>';
