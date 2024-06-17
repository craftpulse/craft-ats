<?php

namespace craftpulse\ats\services;

use Craft;
use craft\elements\Category;
use craftpulse\ats\Ats;
use yii\base\Component;

/**
 * Location Service service
 */
class LocationService extends Component
{
    /**
     * Get place bij postCode from ATS
     * @param string $postCode
     * @return bool
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
     * Add place to the categories
     * @param string|null $postCode
     * @return int|null
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
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

    public function getProvinceByName(string $name): ?Category
    {
        return Category::find()
            ->group(Ats::$plugin->settings->provincesHandle)
            ->title($name)
            ->anyStatus()
            ->one();
    }

    public function upsertProvince(string $name): ?int
    {
        if (is_null($name)) {
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
