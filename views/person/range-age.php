<?php
use miloschuman\highcharts\Highcharts;
use yii\web\JsExpression;
use kartik\grid\GridView;
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use andahrm\structure\models\FiscalYear;

$this->title =  Yii::t('andahrm/report', 'Range Age');
$this->params['breadcrumbs'][] = ['label' =>  Yii::t('andahrm/report', 'Report'), 'url' => ['/report/default']];
$this->params['breadcrumbs'][] = ['label' =>  Yii::t('andahrm/report', 'Person'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<?php
$data = [];
$selected = false;

foreach ($models as $key => $value) {
    //$arr = ['name' => $type->title, 'y' => count($type->positions)];
    $arr = ['name' => $value['title'].' ('.$value['count_person'].')', 'y' => $value['count_person']*1];
    $data[] = $arr;
}



?>



<?php
echo Highcharts::widget([
    'options' => [
        'chart' => [
            'type' => 'pie',
            'plotBackgroundColor' => null,
            'plotBorderWidth' => null,
            'plotShadow' => false,
        ],
        'title' => ['text' => 'ร้อยละของบุคลากรแบ่งตามช่วงอายุ'],
        'tooltip' => [
            'pointFormat' => '{series.name}: <b>{point.y}</b>'
        ],
        'plotOptions' => [
            'pie' => [
                'allowPointSelect' => true,
                'cursor' => 'pointer',
                'dataLabels' => [
                    'enabled' => true,
                    'format' => '<b>{point.name}</b>: {point.percentage:.2f} %',
                ],
                'showInLegend' => true
            ]
        ],
        'series' => [
            [
                'name' => 'Brands',
                'colorByPoint' => true,
                'data' => $data
            ]
        ]
    ]
]);
$directoryAsset = Yii::$app->assetManager->getPublishedUrl('@bower/highcharts');
$this->registerJsFile($directoryAsset.'/modules/exporting.js', ['depends' => ['\miloschuman\highcharts\HighchartsAsset']]);
?>


<?=GridView::widget([
    'dataProvider'=>$dataProvider,
    'showPageSummary' => true,
    'columns'=>[
        ['class' => 'kartik\grid\SerialColumn'],
        [
            'attribute'=>'title',
            'label'=>'ช่วงอายุ',
            'pageSummary'=>Yii::t('andahrm','Total'),
            
        ],
        [
            'attribute'=>'count_person',
            'label'=>Yii::t('andahrm', 'Count Person'),
            'content'=>function($model){
                     $where['start_age'] = $model['start'];
                     $where['end_age'] = $model['end'];
                     return Html::a($model['count_person'],['/report/person','PersonSearch'=>$where]);
                 },
            'pageSummary'=>true,
        ]
        ]
    ]);
    
    ?>