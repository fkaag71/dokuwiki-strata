<?php
/**
 * Strata Basic, table plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     François KAAG (francois.kaag@cardynal.fr)
 * Derived from the original table command
 */

if (!defined('DOKU_INC')) die('Meh.');

/**
 * Radar syntax for basic query handling.
 */
class syntax_plugin_strata_radar extends syntax_plugin_strata_select {
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<radar'.$this->helper->fieldsShortPattern().'* *>\s*?\n.+?\n\s*?</radar>',$mode, 'plugin_strata_radar');
    }

    function getUISettingUI($hasUIBlock) {
        return array('choices' => array('none' => array('none', 'no', 'n'), 'generic' => array('generic', 'g'), 'radar' => array('radar', 't')), 'default' => 'radar');
    }

    function handleHeader($header, &$result, &$typemap) {
        return preg_replace('/(^<radar)|( *>$)/','',$header);
    }

    function render($mode, Doku_Renderer $R, $data) {
        if($data == array() || isset($data['error'])) {
            if($mode == 'xhtml' || $mode == 'odt') {
                $R->table_open();
                $R->tablerow_open();
                $R->tablecell_open();
                $this->displayError($mode, $R, $data);
                $R->tablecell_close();
                $R->tablerow_close();
                $R->table_close();
            }
            return;
        }

        $query = $this->prepareQuery($data['query']);

        // execute the query
        $result = $this->triples->queryRelations($query);

        // prepare all columns
        foreach($data['fields'] as $meta) {
            $fields[] = array(
                'variable'=>$meta['variable'],
                'caption'=>$meta['caption'],
                'type'=>$this->util->loadType($meta['type']),
                'typeName'=>$meta['type'],
                'hint'=>$meta['hint'],
                'aggregate'=>$this->util->loadAggregate($meta['aggregate']),
                'aggregateHint'=>$meta['aggregateHint']
            );
        }

        if($mode == 'xhtml' || $mode == 'odt') {
			$labelSet=array_column($fields,'caption');
			$labelSet[]=$labelSet[0];
			$labels=json_encode($labelSet);
            if($mode == 'xhtml') { $R->doc .= '</thead>'.DOKU_LF; }
            if($result != false) {
                // render each row
                $itemcount = 0;
                foreach($result as $row) {
					$valueSet=[];
                    foreach($fields as $f) {
						$valueSet[]= intval($f['aggregate']->aggregate($row[$f['variable']],$f['aggregateHint'])[0]);
                    }
					$valueSet[]=$valueSet[0];
					$values = json_encode($valueSet);
           if($mode == 'xhtml') { $R->doc .= '</tbody>'.DOKU_LF; }
                }
                $result->closeCursor();
            } else {
				$R->table_open();
                $R->tablerow_open();
                $R->tablecell_open(count($fields));
                $R->emphasis_open();
                $R->cdata(sprintf($this->helper->getLang('content_error_explanation'),'Strata table'));
                $R->emphasis_close();
                $R->tablecell_close();
                $R->tablerow_close();
				$R->table_close();
            }
			$uid=uniqid();
			
			$R->doc .= "
<script>function draw() {
data = [{type: 'scatterpolar',r: ".$values.",theta: ".$labels.",fill: 'toself'}]
layout = {polar: {radialaxis: {visible: true,range: [0, 100]}},showlegend: false}
graph = document.getElementById('".$uid."');
Plotly.newPlot(graph, data, layout)
}
window.onload=draw;
</script>
<style>svg {height : auto}</style>
<div id='".$uid."'></div>";
            return true;
        } elseif($mode == 'metadata') {
            if($result == false) return;

            // render all rows in metadata mode to enable things like backlinks
            foreach($result as $row) {
                foreach($fields as $f) {
                    $this->util->renderField($mode, $R, $this->triples, $f['aggregate']->aggregate($row[$f['variable']],$f['aggregateHint']), $f['typeName'], $f['hint'], $f['type'], $f['variable']);
                }
            }
            $result->closeCursor();

            return true;
        }

        return false;
    }
}
