<?php

namespace mmikkel\cpfieldinspect\helpers;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\elements\User;
use craft\commerce\elements\Product;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\models\EntryType;
use yii\base\InvalidConfigException;

class CpFieldInspectHelper
{

    /**
     * @param string|null $url
     * @return string
     * @throws \craft\errors\SiteNotFoundException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public static function getRedirectUrl(?string $url = null): string
    {
        if (!$url) {
            $url = implode('?', array_filter([implode('/', Craft::$app->getRequest()->getSegments()), Craft::$app->getRequest()->getQueryStringWithoutPath()]));
        }
        // Special case for globals – account for their handles being edited before redirecting back
        $segments = explode('/', $url);
        if (($segments[0] ?? null) === 'globals') {
            if (Craft::$app->getIsMultiSite()) {
                $siteHandle = $segments[1] ?? null;
                $globalSetHandle = $segments[2] ?? null;
            } else {
                $siteHandle = Craft::$app->getSites()->getPrimarySite()->handle;
                $globalSetHandle = $segments[1] ?? null;
            }
            if ($siteHandle && $globalSetHandle && $globalSet = GlobalSet::find()->site($siteHandle)->handle($globalSetHandle)->one()) {
                $url = "edit/$globalSet->id?site=$siteHandle";
            }
        }
        return Craft::$app->getSecurity()->hashData($url);
    }

    /**
     * @throws InvalidConfigException
     */
    public static function renderEditSourceLink(array $context): string
    {
        $element = $context['element'] ?? $context['entry'] ?? $context['asset'] ?? $context['globalSet'] ?? $context['user'] ?? $context['category'] ?? $context['product'] ?? null;
        if (empty($element)) {
            return '';
        }
        return static::getEditElementSourceButton($element);
    }

    /**
     * @param ElementInterface|null $element
     * @param array $attributes
     * @param string|null $size
     * @return string
     * @throws \yii\base\InvalidConfigException
     */
    public static function getEditElementSourceButton(?ElementInterface $element, array $attributes = [], ?string $size = null): string
    {
        if (empty($element)) {
            return '';
        }
        $html = '';
        if ($element instanceof Entry) {
            $typeIds = array_map(static fn (EntryType $entryType) => (int)$entryType->id, $element->getAvailableEntryTypes());
            if (empty($typeIds)) {
                return '';
            }
            foreach ($typeIds as $typeId) {
                $html .= static::_getEditSourceButtonHtml('Edit Entry Type', "settings/entry-types/$typeId", [
                    'style' => $typeId !== (int)$element->typeId ? 'display:none;' : false,
                    'data-typeid' => $typeId,
                ], $size);
            }
        } else if ($element instanceof Asset) {
            $html = static::_getEditSourceButtonHtml('Edit Volume', "'settings/assets/volumes/{$element->volumeId}");
        } else if ($element instanceof GlobalSet) {
            $html = static::_getEditSourceButtonHtml('Edit Global Set', "settings/globals/{$element->id}");
        } else if ($element instanceof User) {
            $html = static::_getEditSourceButtonHtml('Edit Users Settings', 'settings/users/fields', [
                'style' => 'margin-top:20px;',
            ]);
        } else if ($element instanceof Category) {
            $html = static::_getEditSourceButtonHtml('Edit Category Group', "settings/categories/{$element->groupId}");
        } else if (class_exists(Product::class) && $element instanceof Product) {
            $html = static::_getEditSourceButtonHtml('Edit Product Type', "commerce/settings/producttypes/{$element->typeId}");
        }
        if (empty($html)) {
            return '';
        }
        return Html::tag('div', $html, [
            ...$attributes,
            'class' => [
                'cp-field-inspect-sourcebtn-wrapper',
                ...$attributes['class'] ?? [],
            ],
        ]);
    }

    /**
     * @param string $label
     * @param string $path
     * @param array $attributes
     * @param string|null $size
     * @return string
     */
    private static function _getEditSourceButtonHtml(string $label, string $path, array $attributes = [], ?string $size = null): string
    {
        return Html::tag('a', Craft::t('cp-field-inspect', $label), [
            'href' => UrlHelper::cpUrl($path),
            'class' => [
                'btn settings icon',
                $size === 'small' ? 'small' : null,
            ],
            'data-cpfieldlinks-sourcebtn' => true,
            ...$attributes,
        ]);
    }

}
