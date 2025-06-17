<?php
// Rankmath Tests deaktivieren
add_filter('rank_math/researches/tests', function ($tests, $type) {
	unset($tests['titleHasNumber']);
    unset($tests['contentHasTOC']);
    unset($tests['titleHasPowerWords']);
    unset($tests['hasContentAI']);
	return $tests;
}, 10, 2 );