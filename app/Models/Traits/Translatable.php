<?php

namespace App\Models\Traits;

use Illuminate\Support\Facades\App;

trait Translatable
{
    /**
     * Override getAttribute to support English translation automatically.
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        // If current locale is English
        if (App::getLocale() === 'en') {
            $translatedKey = $key . '_en';
            // If the model has the translation attribute loaded and it is not empty
            if (array_key_exists($translatedKey, $this->attributes)) {
                $translatedValue = parent::getAttribute($translatedKey);
                if (!is_null($translatedValue) && $translatedValue !== '') {
                    return $translatedValue;
                }
            }
        }

        return $value;
    }
}
