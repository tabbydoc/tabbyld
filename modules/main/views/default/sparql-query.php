<?php

/* @var $this yii\web\View */

use yii\helpers\Html;
use BorderCloud\SPARQL\SparqlClient;

$this->title = Yii::t('app', 'SPARQL_QUERY_PAGE_TITLE');
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="main-default-sparql-query">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php
        $endpoint = "http://dbpedia.org/sparql";
        $sc = new SparqlClient();
        $sc->setEndpointRead($endpoint);
        $query = "
            PREFIX dbpo: <http://dbpedia.org/ontology/> SELECT *
            WHERE
            {
             ?e dbpo:series         <http://dbpedia.org/resource/The_Sopranos>.
             ?e dbpo:releaseDate   ?date.
             ?e dbpo:episodeNumber  ?number.
             ?e dbpo:seasonNumber   ?season.
            }
            ORDER BY DESC(?date)
            ";
        $rows = $sc->query($query, 'rows');
        $err = $sc->getErrors();
        if ($err) {
            print_r($err);
            throw new Exception(print_r($err, true));
        }
        echo '<table class="table table-striped table-bordered">';
        echo '<tr>';
        foreach ($rows["result"]["variables"] as $variable) {
            echo '<td><b>';
            printf("%-20.20s", $variable);
            echo '</b></td>';
        }
        echo '</tr>';
        foreach ($rows["result"]["rows"] as $row) {
            echo '<tr>';
            foreach ($rows["result"]["variables"] as $variable)
                echo '<td>' . $row[$variable] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    ?>

</div>