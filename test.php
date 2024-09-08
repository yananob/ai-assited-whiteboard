<?php

declare(strict_types=1);

$shapeText = "[3458764597396431193]フィードバック: 「不具合が多い」という表現は、具体性に欠けます。どのような不具合が多いのか、具体的な事例や詳細を追加すると、より明確で誤解を生むことが少なくなります。";
preg_match('/^\[([0-9]+)\]/', $shapeText, $matches);

var_dump($matches);
