<?php
/**
 * DokuWiki Plugin strata (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Brend Wanders <b.wanders@utwente.nl>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die('Meh.');

/**
 * This action component exists to allow the definition of
 * the type autoloader.
 */
class action_plugin_strata extends DokuWiki_Action_Plugin {

    /**
     * Register function called by DokuWiki to allow us
     * to register events we're interested in.
     *
     * @param controller object the controller to register with
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('IO_WIKIPAGE_WRITE', 'BEFORE', $this, '_io_page_write');
        $controller->register_hook('PARSER_METADATA_RENDER', 'BEFORE', $this, '_parser_metadata_render_before');
        $controller->register_hook('STRATA_PREVIEW_METADATA_RENDER', 'BEFORE', $this, '_parser_metadata_render_before');
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, '_preview_before');
        $controller->register_hook('TPL_ACT_RENDER', 'AFTER', $this, '_preview_after');

        $controller->register_hook('PARSER_METADATA_RENDER', 'AFTER', $this, '_parser_metadata_render_after');
        $controller->register_hook('STRATA_PREVIEW_METADATA_RENDER', 'AFTER', $this, '_parser_metadata_render_after');
    }


    /**
     * Triggers before preview xhtml render,
     * allows plugins to metadata render on the preview.
     */
    public function _preview_before(&$event, $param) {
        global $ACT;
        global $TEXT;
        global $SUF;
        global $PRE;
        global $ID;
        global $METADATA_RENDERERS;

        if($ACT == 'preview') {
            $triples =& plugin_load('helper', 'strata_triples');
            $triples->beginPreview();

            $text = $PRE.$TEXT.$SUF;
            $orig = p_read_metadata($ID);

            // store the original metadata in the global $METADATA_RENDERERS so p_set_metadata can use it
            $METADATA_RENDERERS[$ID] =& $orig;

            // add an extra key for the event - to tell event handlers the page whose metadata this is
            $orig['page'] = $ID;
            $evt = new Doku_Event('STRATA_PREVIEW_METADATA_RENDER', $orig);
            if ($evt->advise_before()) {
                // get instructions
                $instructions = p_get_instructions($text);
                if(is_null($instructions)){
                    unset($METADATA_RENDERERS[$ID]);
                    return null; // something went wrong with the instructions
                }

                // set up the renderer
                $renderer = new renderer_plugin_strata();
                $renderer->meta =& $orig['current'];
                $renderer->persistent =& $orig['persistent'];

                // loop through the instructions
                foreach ($instructions as $instruction){
                    // execute the callback against the renderer
                    call_user_func_array(array(&$renderer, $instruction[0]), (array) $instruction[1]);
                }

                $evt->result = array('current'=>&$renderer->meta,'persistent'=>&$renderer->persistent);
            }
            $evt->advise_after();

            // clean up
            unset($METADATA_RENDERERS[$ID]);
        }
    }


    public function _preview_after(&$event, $param) {
        global $ACT;

        if($ACT == 'preview') {
            $triples =& plugin_load('helper', 'strata_triples');
            $triples->endPreview();
        }
    }

    /**
     * Triggered whenever a page is written. We need to handle
     * this event because metadata is not rendered if a page is removed.
     */
    public function _io_page_write(&$event, $param) {
        // only remove triples if page is a new revision, or if it is removed
        if($event->data[3] == false || $event->data[0][1] == '') {
            $id = ltrim($event->data[1].':'.$event->data[2],':');
            $this->_purge_data($id);
        }
    }

    /**
     * Triggered before metadata is going to be rendered. We
     * remove triples previously generated by the page that is going to
     * be rendered so we don't get duplicate entries.
     */
    public function _parser_metadata_render_before(&$event, $param) {
        $this->_purge_data($event->data['page']);
    }

    /**
     * Triggered after metadata has been rendered.
     * We check the fixTitle flag, and if it is present, we
     * add the entry title.
     */
    public function _parser_metadata_render_after(&$event, $param) {
        $id = $event->data['page'];

        $current =& $event->data['current'];

        if(isset($current['strata']['fixTitle']) && $current['strata']['fixTitle']) {
            // get helpers
            $triples =& plugin_load('helper', 'strata_triples');
            $util =& plugin_load('helper', 'strata_util');

            $title = $current['title'] ?? null;
            if(!$title) {
                $title = noNS($id);
            }

            $title = $util->loadType('text')->normalize($title,'');

            $triples->addTriple($id, $util->getTitleKey(), $title, $id);
        }
    }

    /**
     * Purges the data for a single page id.
     *
     * @param id string the page that needs to be purged
     */
    private function _purge_data($id) {
        // get triples helper
        $triples =& plugin_load('helper', 'strata_triples');

        // remove all triples defined in this graph
        $triples->removeTriples(null,null,null,$id);
    }
}

/**
 * Strata 'pluggable' autoloader. This function is responsible
 * for autoloading classes that should be pluggable by external
 * plugins.
 *
 * @param fullname string the name of the class to load
 */
function plugin_strata_autoload($fullname) {
    static $classes = null;
    if(is_null($classes)) $classes = array(
        'strata_exception'         => DOKU_PLUGIN.'strata/lib/strata_exception.php',
        'strata_querytree_visitor' => DOKU_PLUGIN.'strata/lib/strata_querytree_visitor.php',
        'plugin_strata_type'       => DOKU_PLUGIN.'strata/lib/strata_type.php',
        'plugin_strata_aggregate'  => DOKU_PLUGIN.'strata/lib/strata_aggregate.php',
   );

    if(isset($classes[$fullname])) {
        require_once($classes[$fullname]);
        return;
    }

    // only load matching components
    if(preg_match('/^plugin_strata_(type|aggregate)_(.*)$/',$fullname, $matches)) {
        // use descriptive names
        list(,$kind,$name) = $matches;

        // glob to find the required file
        $filenames = glob(DOKU_PLUGIN."*/{$kind}s/{$name}.php");
        if($filenames === false || count($filenames) == 0) {
            // if we have no file, fake an implementation
            eval("class $fullname extends plugin_strata_{$kind} { };");
        } else {
            // include the file
            require_once $filenames[0];
            // If the class still does not exist, the required file does not define the class, so we fall back
            // to the default
            if(!class_exists($fullname)) {
                eval("class $fullname extends plugin_strata_{$kind} { };");
            }
        }

        return;
    }
}

// register autoloader with SPL loader stack
spl_autoload_register('plugin_strata_autoload');
