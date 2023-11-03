<?php
/**
 * @author     François KAAG (francois.kaag@cardynal.fr)
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die('Meh.');

/**
 * New enum type for Strata
 */
class plugin_strata_type_enum extends plugin_strata_type {
    function render($mode, &$R, &$triples, $value, $hint) {
	global $ID;
            // use the hint if available
	$scope=getNS($ID);
	if ($scope != "") $scope .=":";
	if (str_starts_with($hint,':'))
	   { $hint = substr($hint,1); } 
	else 
	   { $hint = $scope.$hint; }

	$labels = $triples ->fetchTriples ($hint,null,$value,null,$null);
	$label = ($labels? $labels[0]['predicate']: '#NA');

	$R->internallink($hint,$label);
    return true;
    }

    function getInfo() {
        return array(
            'desc'=>'Displays a value or a key  by using the corresponding prefix  in a given data fragment',
            'tags'=>array('string'),
            'hint'=>'data fragment'
        );
    }
}

