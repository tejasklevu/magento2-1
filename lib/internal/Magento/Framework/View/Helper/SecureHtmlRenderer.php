<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\Framework\View\Helper;

use Magento\Framework\Math\Random;
use Magento\Framework\View\Helper\SecureHtmlRender\EventHandlerData;
use Magento\Framework\View\Helper\SecureHtmlRender\HtmlRenderer;
use Magento\Framework\View\Helper\SecureHtmlRender\SecurityProcessorInterface;
use Magento\Framework\View\Helper\SecureHtmlRender\TagData;

/**
 * Render HTML elements with consideration to application security.
 */
class SecureHtmlRenderer
{
    /**
     * @var HtmlRenderer
     */
    private $renderer;

    /**
     * @var SecurityProcessorInterface[]
     */
    private $processors;

    /**
     * @var Random
     */
    private $random;

    /**
     * @param HtmlRenderer $renderer
     * @param Random $random
     * @param SecurityProcessorInterface[] $processors
     */
    public function __construct(HtmlRenderer $renderer, Random $random, array $processors = [])
    {
        $this->renderer = $renderer;
        $this->random = $random;
        $this->processors = $processors;
    }

    /**
     * Renders HTML tag while possibly modifying or using it's attributes and content for security reasons.
     *
     * @param string $tagName Like "script" or "style"
     * @param string[] $attributes Attributes map, values must not be escaped.
     * @param string|null $content Tag's content.
     * @param bool $textContent Whether to treat the tag's content as text or HTML.
     * @return string
     */
    public function renderTag(
        string $tagName,
        array $attributes,
        ?string $content = null,
        bool $textContent = true
    ): string {
        $tag = new TagData($tagName, $attributes, $content, $textContent);
        foreach ($this->processors as $processor) {
            $tag = $processor->processTag($tag);
        }

        return $this->renderer->renderTag($tag);
    }

    /**
     * Render event listener as an HTML attribute while possibly modifying or using it's content for security reasons.
     *
     * @param string $eventName Full attribute name like "onclick".
     * @param string $javascript
     * @return string
     */
    public function renderEventListener(string $eventName, string $javascript): string
    {
        $event = new EventHandlerData($eventName, $javascript);
        foreach ($this->processors as $processor) {
            $event = $processor->processEventHandler($event);
        }

        return $this->renderer->renderEventHandler($event);
    }

    /**
     * Render event listener script as a separate tag instead of an attribute.
     *
     * @param string $eventName Full event name like "onclick".
     * @param string $attributeJavascript JS that would've gone to an HTML attribute.
     * @param string $elementSelector CSS selector for the element we handle the event for.
     * @return string Result script tag.
     */
    public function renderEventListenerAsTag(
        string $eventName,
        string $attributeJavascript,
        string $elementSelector
    ): string {
        $eventName = mb_strtolower(mb_substr($eventName, 2));
        $listenerFunction = 'eventListener' .$this->random->getRandomString(32);
        $elementName = 'listenedElement' .$this->random->getRandomString(32);
        $script = <<<script
            function {$listenerFunction} () {
                {$attributeJavascript};
            }
            let {$elementName} = document.querySelector("{$elementSelector}");
            if ({$elementName}) {
                {$elementName}.addEventListener("{$eventName}", (event) => {$listenerFunction}.apply(event.target));
            }
script;

        return $this->renderTag('script', ['type' => 'text/javascript'], $script, false);
    }
}
