<?php
/**
 * Config form
 *
 * @package OhanaClient
 * @author Michael Slone
 * @copyright Copyright (C) 2015 Michael Slone <m.slone@gmail.com>
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

$view = get_view()
?>

<div class="field">
  <div class="two columns alpha">
    <label for="ohana_library_path">Path to Ohana library</label>
  </div>
  <div class="inputs five columns omega">
    <p class="explanation">The absolute path to api/ohana.php on the server.</p>
    <?php echo $view->formText('ohana_library_path', get_option('ohana_library_path')); ?>
  </div>
</div>
