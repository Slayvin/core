<?php

namespace Bridge\Core\Models;

use Yii;
use yii\base\InvalidArgumentException;
use yii\db\BaseActiveRecord;
use yii\db\Exception;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "settings_group".
 *
 * @property integer $id
 * @property string $key
 * @property string $title
 * @property string $description
 * @property string $icon
 * @property integer $position
 *
 * @property Settings[] $settings
 *
 * @method bool movePrev() Moves owner record by one position towards the start of the list.
 * @method bool moveNext() Moves owner record by one position towards the end of the list.
 * @method bool moveFirst() Moves owner record to the start of the list.
 * @method bool moveLast() Moves owner record to the end of the list.
 * @method bool moveToPosition($position) Moves owner record to the specific position.
 * @method bool getIsFirst() Checks whether this record is the first in the list.
 * @method bool getIsLast() Checks whether this record is the the last in the list.
 * @method BaseActiveRecord|static|null findPrev() Finds record previous to this one.
 * @method BaseActiveRecord|static|null findNext() Finds record next to this one.
 * @method BaseActiveRecord|static|null findFirst() Finds the first record in the list.
 * @method BaseActiveRecord|static|null findLast() Finds the last record in the list.
 * @method mixed beforeInsert($event) Handles owner 'beforeInsert' owner event, preparing its positioning.
 * @method mixed beforeUpdate($event) Handles owner 'beforeInsert' owner event, preparing its possible re-positioning.
 */
class SettingsGroup extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'settings_group';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['key'], 'required'],
            [['description'], 'string'],
            [['position'], 'integer'],
            [['key', 'title', 'icon'], 'string', 'max' => 255],
            [['key'], 'unique', 'targetAttribute' => ['key']]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('bridge', 'ID'),
            'title' => Yii::t('bridge', 'Group name'),
            'description' => Yii::t('bridge', 'Group description'),
            'icon' => Yii::t('bridge', 'Group icon'),
            'position' => Yii::t('bridge', 'Order'),
            'key' => Yii::t('bridge', 'Key'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSettings()
    {
        return $this->hasMany(Settings::class, ['group_id' => 'id']);
    }

    /**
     * @inheritdoc
     * @return \Bridge\Core\Models\Query\SettingsGroupQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new \Bridge\Core\Models\Query\SettingsGroupQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'positionSort' => [
                'class' => 'yii2tech\ar\position\PositionBehavior',
                'positionAttribute' => 'position',
            ],
        ];
    }

    /**
     * Looks for setting with provided `$key`
     *
     * @param string $key key to be looking for
     * @return Settings
     * @throws InvalidArgumentException when settings with key provided wasn't found
     */
    public static function get($key)
    {
        if (!empty(Settings::$prevSettings[$key])) {
            $model = Settings::$prevSettings[$key];
        } elseif((\Yii::$app->getModule('admin')->settingsCaching)) {
            $cacheKey = \Yii::$app->getModule('admin')->settingsCacheKey;
            $model = \Yii::$app->cache->getOrSet($cacheKey . '-' . $key, function () use ($key) {
                return Settings::find()->key($key)->one();
            }, 86400);
        } else {
            $model = Settings::find()->key($key)->one();
        }

        if (empty($model)) {
            throw new InvalidArgumentException("Setting with key '{$key}' wasn't found. Try creating it first or run getOrCreate method");
        }

        Settings::$prevSettings[$key] = $model;

        $isSettingTranslated = SettingsTranslation::find()->where(['settings_id' => $model->id])->exists();

        if (!$isSettingTranslated) {
            self::createTranslations($model);
        }

        return $model;
    }

    /**
     * Creates setting with provided params
     *
     * @param array $params
     * @return Settings
     */
    public function create($params)
    {
        $model = new Settings($params);
        $model->save();

        self::createTranslations($model);

        Settings::$prevSettings[$model->key] = $model;

        return $model;
    }

    /**
     * Looks for setting with provided `$key`. If not found, creates it with `$defaultParams`
     *
     * @param string $key
     * @param array $defaultParams
     * @return Settings|string
     */
    public function getOrCreate($key, $defaultParams = [])
    {
        try {
            return self::get($key);
        } catch (InvalidArgumentException $e) {
            return self::create(ArrayHelper::merge([
                'key' => $key,
                'title' => $key,
                'type' => Settings::TYPE_STRING,
                'value' => '',
                'group_id' => $this->id
            ], $defaultParams));
        }
    }

    public static function getDropDownData()
    {
        return ArrayHelper::merge(
            [null => 'Разное'],
            ArrayHelper::map(static::find()->orderBy(['position' => SORT_ASC])->all(), 'id', 'title')
        );
    }

    /**
     * Creates setting translations
     *
     * @param Settings $model
     * @return bool
     */
    private static function createTranslations(Settings $model)
    {
        $data = [];

        foreach (Yii::$app->urlManager->languages as $label => $code) {
            $data[] = [
                'lang' => $code,
                'settings_id' => $model->id,
                'value' => $model->value
            ];
        }

        try {
            Yii::$app->db
                ->createCommand()
                ->batchInsert('settings_translations', ['lang', 'settings_id', 'value'], $data)
                ->execute();

            return true;
        } catch (Exception $exception) {
            // TODO: Логировать ошибку создание переводов для настроек
        }

        return false;
    }

    /**
     * @param bool $insert
     * @param array $changedAttributes
     */
    public function afterSave($insert, $changedAttributes){
        parent::afterSave($insert, $changedAttributes);

        /** Кэшируем группу настройки */
        if(\Yii::$app->getModule('admin')->settingsCaching) {
            $cacheKey = \Yii::$app->getModule('admin')->settingsCacheKey;
            \Yii::$app->cache->set($cacheKey . '_group-' . $this->key, $this, 86400);
        }
    }
}