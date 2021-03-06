<?php

namespace Bridge\Core\Models;

use Bridge\Core\Models\Query\MetaPageQuery;
use Yii;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "meta_pages".
 *
 * @property integer $id
 * @property integer $meta_tag_id
 * @property string $module
 * @property string $controller
 * @property string $action
 *
 * @property MetaTag $metaTag
 */
class MetaPage extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'meta_pages';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['meta_tag_id', 'module', 'controller', 'action'], 'required'],
            [['meta_tag_id'], 'integer'],
            [['module', 'controller', 'action'], 'string', 'max' => 255],
            [['meta_tag_id'], 'exist', 'skipOnError' => true, 'targetClass' => MetaTag::class, 'targetAttribute' => ['meta_tag_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('bridge', 'ID'),
            'meta_tag_id' => Yii::t('bridge', 'Meta Tag ID'),
            'module' => Yii::t('bridge', 'Module name'),
            'controller' => Yii::t('bridge', 'Controller name'),
            'action' => Yii::t('bridge', 'Action name'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getMetaTag()
    {
        return $this->hasOne(MetaTag::class, ['id' => 'meta_tag_id']);
    }

    /**
     * @inheritdoc
     * @return MetaPageQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new MetaPageQuery(get_called_class());
    }


    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        /**
         * Сохранение мета-тегов
         */
        $this->metaTag->save();
    }

    /**
     * Получаем мета-теги
     * Если его не существует, то создаем его, с параметрами по-умолчанию
     *
     * Пример для значении по-умолчанию:
     * [
     *  'en-US' => [
     *      'lang' => 'en-US',
     *      'title' => 'Title'
     *  ],
     *  'ru-RU' => [
     *      'lang' => 'ru-RU',
     *      'title' => 'Заголовок'
     *      ],
     *  'kk-KZ' => [
     *      'lang' => 'kk-KZ',
     *      'title' => 'Тақырып'
     *  ]
     * ]
     *
     * @param string $module
     * @param string $controller
     * @param string $action
     * @param array $defaultParams
     * @return MetaTagTranslation|false
     */
    public static function getOrCreate($module, $controller, $action, $defaultParams = [])
    {
        $metaTagTranslation = MetaTagTranslation::find()
            ->joinWith('metaTag.metaPage', false)
            ->where([
                'meta_pages.module' => $module,
                'meta_pages.controller' => $controller,
                'meta_pages.action' => $action,
                'meta_tag_translations.lang' => Yii::$app->language
            ])
            ->one();

        return $metaTagTranslation ?? self::create($module, $controller, $action, $defaultParams);
    }

    /**
     * Создаем объект класса MetaPage, с параметрами по-умолчанию
     *
     * @param $module
     * @param $controller
     * @param $action
     * @param array $defaultParams
     * @return MetaTagTranslation|false
     */
    private static function create($module, $controller, $action, $defaultParams = [])
    {
        $metaPage = self::find()
            ->where([
                'module' => $module,
                'controller' => $controller,
                'action' => $action
            ])
            ->one();

        if (!is_null($metaPage)) {
            $metaTagTranslation = new MetaTagTranslation([
                'meta_tag_id' => $metaPage->meta_tag_id,
                'lang' => Yii::$app->language,
                'title' => ArrayHelper::getValue($defaultParams, Yii::$app->language . '.title', Yii::$app->name),
                'description' => ArrayHelper::getValue($defaultParams, Yii::$app->language . '.description', Yii::$app->name)
            ]);

            return $metaTagTranslation->save() ? $metaTagTranslation : false;
        }

        $metaTag = MetaTag::create($defaultParams);

        if (!$metaTag) {
            return false;
        }

        $metaPage = new self([
            'meta_tag_id' => $metaTag->primaryKey,
            'module' => $module,
            'controller' => $controller,
            'action' => $action
        ]);

        return $metaPage->save() ? $metaTag->translation : false;
    }
}
