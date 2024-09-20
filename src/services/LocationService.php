<?php

namespace craftpulse\ats\services;

use Craft;
use craft\elements\Category;
use craft\errors\ElementNotFoundException;
use craftpulse\ats\Ats;
use Throwable;
use yii\base\Component;
use yii\base\Exception;

/**
 * Location Service service
 */
class LocationService extends Component
{
    /**
     * @param string $postCode
     * @return Category|null
     */
    public function getPlaceByPostCode(string $postCode): ?Category
    {
        return Category::find()
            ->group(Ats::$plugin->settings->placesHandle)
            ->postCode($postCode)
            ->anyStatus()
            ->one();
    }

    /**
     * @param string|null $postCode
     * @return int|null
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function upsertPlace(?string $postCode): ?int
    {
        if (is_null($postCode)) {
            return null;
        }

        // fetch category
        $category = $this->getPlaceByPostCode($postCode);

        // if category doesn't exist -> create
        if (is_null($category)) {
            $categoryGroup = Craft::$app->categories->getGroupByHandle(Ats::$plugin->settings->placesHandle);

            if ($categoryGroup) {
                $category = new Category([
                    'groupId' => $categoryGroup->id
                ]);
            }
        }

        if (!is_null($category) && $category->postCode !== $postCode)
        {
            $mapboxService = new MapboxService();

            $location = $mapboxService->getAddress($postCode . ' België');
            $place = $location['properties']['context']['locality']['name'] ?? $location['properties']['context']['place']['name'] ?? '';

            if ($place) {
                $coords = $mapboxService->getCoordsByLocation($location);

                // save category fields
                $category->postCode = $postCode;
                $category->latitude = $coords[1] ?? '';
                $category->longitude = $coords[0] ?? '';
                $category->title = $place;
            }
        }

        $category->setEnabledForSite($category->getSupportedSites());
        $category->enabled = true;

        // save element
        $saved = Craft::$app->getElements()->saveElement($category);

        // return category
        return $saved ? $category->id : null;
    }

    /**
     * @param string $name
     * @return Category|null
     */
    public function getProvinceByName(string $name): ?Category
    {
        return Category::find()
            ->group(Ats::$plugin->settings->provincesHandle)
            ->title($name)
            ->anyStatus()
            ->one();
    }

    /**
     * @param string $name
     * @return int|null
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     */
    public function upsertProvince(string $name): ?int
    {
        if (empty($name)) {
            return null;
        }

        // fetch category
        $category = $this->getProvinceByName($name);

        // if category doesn't exist -> create
        if (is_null($category)) {
            $categoryGroup = Craft::$app->categories->getGroupByHandle(Ats::$plugin->settings->provincesHandle);

            if ($categoryGroup) {
                $category = new Category([
                    'groupId' => $categoryGroup->id
                ]);
            }
        }

        if (!is_null($category)) {
            // save category fields
            $category->title = $name;

            $category->setEnabledForSite($category->getSupportedSites());

            // save element
            $saved = Craft::$app->getElements()->saveElement($category);

            // return category
            return $saved ? $category->id : null;
        }

        return null;
    }
}
