<?php

use yii\helpers\Inflector;
use yii\helpers\StringHelper;
use naffiq\bridge\gii\helpers\ColumnHelper;

/* @var $this yii\web\View */
/* @var $generator \naffiq\bridge\gii\crud\Generator */

$urlParams = $generator->generateUrlParams();
$nameAttribute = $generator->getNameAttribute();
$contexts = $generator->getContexts();

echo "<?php\n";
?>

<?php if ($generator->indexWidgetType === 'grid'): ?>
use yii\grid\GridView;
use yii2tech\admin\grid\ActionColumn;
<?php else: ?>
use yii\widgets\ListView;
<?php endif ?>

/* @var $this yii\web\View */
/* @var $searchModel <?= !empty($generator->searchModelClass) ? ltrim($generator->searchModelClass, '\\') : 'yii\base\Model' ?> */
/* @var $dataProvider yii\data\ActiveDataProvider */
<?php if (!empty($contexts)): ?>
/* @var $controller <?= $generator->controllerClass ?>|yii2tech\admin\behaviors\ContextModelControlBehavior */

$controller = $this->context;
$contextUrlParams = $controller->getContextQueryParams();
<?php endif ?>

$this->title = <?= $generator->generateString(Inflector::pluralize(Inflector::camel2words(StringHelper::basename($generator->modelClass)))) ?>;
<?php if (!empty($contexts)): ?>
foreach ($controller->getContextModels() as $name => $contextModel) {
    $this->params['breadcrumbs'][] = ['label' => $name, 'url' => $controller->getContextUrl($name)];
    $this->params['breadcrumbs'][] = ['label' => $contextModel->id, 'url' => $controller->getContextModelUrl($name)];
}
<?php endif ?>
$this->params['breadcrumbs'][] = $this->title;
<?php if (!empty($contexts)): ?>
$this->params['contextMenuItems'] = [
    array_merge(['create'], $contextUrlParams)
];
<?php else: ?>
$this->params['contextMenuItems'] = [
    ['create']
];
<?php endif ?>
?>
<?php if (!empty($generator->searchModelClass) && $generator->indexWidgetType !== 'grid'): ?>
<?= "\n    <?php " ?>echo $this->render('_search', ['model' => $searchModel]); ?>
<?php endif; ?>

<?php if ($generator->indexWidgetType === 'grid'): ?>
<?= "<?= " ?>GridView::widget([
    'dataProvider' => $dataProvider,
    'options' => ['class' => 'grid-view table-responsive'],
    <?= !empty($generator->searchModelClass) ? "'filterModel' => \$searchModel,\n    'columns' => [\n" : "'columns' => [\n"; ?>
        ['class' => 'yii\grid\SerialColumn'],

<?php
$count = 0;
if (($tableSchema = $generator->getTableSchema()) === false) {
    foreach ($generator->getColumnNames() as $name) {
        if (++$count < 6) {
            echo "        '" . $name . "',\n";
        } else {
            echo "        // '" . $name . "',\n";
        }
    }
} else {
    foreach ($tableSchema->columns as $column) {
        $format = $generator->generateGridColumnFormat($column);
        if ($format === false) continue;

        echo '        ' . (++$count > 5 ? '// ' : '');
        if (is_array($format)) {
            echo "[\n";
            foreach ($format as $item => $value) {
                echo ColumnHelper::pushTab(3) . "'{$item}' => " . (is_string($value) ? "'{$value}'" : $value) . ",\n";
            }
            echo ColumnHelper::pushTab(2) . "],\n";
        } else {
            echo "'" . $column->name . ($format === 'text' ? "" : ":" . $format) . "',\n";
        }
    }
}
?>

        [
            'class' => ActionColumn::className(),
        ],
    ],
]); ?>
<?php else: ?>
<?= "<?= " ?>ListView::widget([
    'dataProvider' => $dataProvider,
    'itemOptions' => ['class' => 'item'],
    'itemView' => function ($model, $key, $index, $widget) {
        return Html::a(Html::encode($model-><?= $nameAttribute ?>), array_merge(['view', <?= $urlParams ?>], $contextUrlParams));
    },
]) ?>
<?php endif; ?>