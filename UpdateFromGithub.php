require_once __DIR__ . '/plugin-update-checker-master/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/EnthalpyEntertainment/NightShadeForWordpress/',
    __FILE__,
    'nightshade-for-wordpress'
);

// Set branch
$updateChecker->setBranch('main');

