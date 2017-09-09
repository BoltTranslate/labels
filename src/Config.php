<?php

namespace Bolt\Extension\Bolt\Labels;

use Bolt\Collection\Bag;

class Config
{
    /** @var Bag */
    protected $languages;
    /** @var string */
    protected $default;
    /** @var boolean */
    protected $add_missing;
    /** @var boolean */
    protected $use_fallback;
    /** @var boolean */
    protected $show_menu;

    /**
     * Constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->languages = Bag::from($config['languages']);
        $this->default = $config['default'];
        $this->add_missing = $config['add_missing'];
        $this->use_fallback = $config['use_fallback'];
        $this->show_menu = $config['show_menu'];
    }

    /**
     * @return Bag
     */
    public function getLanguages()
    {
        return $this->languages;
    }

    /**
     * @return string
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * @return boolean
     */
    public function isAddMissing()
    {
        return $this->add_missing;
    }

    /**
     * @return boolean
     */
    public function isUseFallback()
    {
        return $this->use_fallback;
    }

    /**
     * @return boolean
     */
    public function isShowMenu()
    {
        return $this->show_menu;
    }
}
