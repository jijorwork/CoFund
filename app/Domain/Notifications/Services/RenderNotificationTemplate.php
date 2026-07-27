<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Models\NotificationTemplate;
use App\Domain\Notifications\ValueObjects\RenderedNotification;
use InvalidArgumentException;

class RenderNotificationTemplate
{
    private const PLACEHOLDER_PATTERN = '/\{\{\s*([a-z0-9_.]+)\s*\}\}/i';

    public function __construct(private readonly NotificationPlaceholderRegistry $placeholders) {}

    /** @param array<string, scalar|null> $values */
    public function render(NotificationTemplate $template, array $values): RenderedNotification
    {
        $this->placeholders->validate($template->event_key, $values);

        return new RenderedNotification(
            $this->renderValue($template->subject_template, $values),
            $this->renderValue($template->title_template, $values),
            $this->renderValue($template->body_template, $values) ?? '',
            $this->renderValue($template->action_label_template, $values),
            $this->safeActionUrl($this->renderValue($template->action_url_template, $values)),
        );
    }

    /** @param array<string, scalar|null> $values */
    private function renderValue(?string $template, array $values): ?string
    {
        if ($template === null) {
            return null;
        }

        $rendered = preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            static fn (array $matches): string => e((string) ($values[$matches[1]] ?? '')),
            $template,
        );

        if ($rendered === null || preg_match(self::PLACEHOLDER_PATTERN, $rendered) === 1) {
            throw new InvalidArgumentException('Notification template contains unresolved placeholders.');
        }

        return $rendered;
    }

    private function safeActionUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        $actionHost = parse_url($url, PHP_URL_HOST);
        $applicationHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (
            filter_var($url, FILTER_VALIDATE_URL) !== false
            && is_string($actionHost)
            && is_string($applicationHost)
            && hash_equals(mb_strtolower($applicationHost), mb_strtolower($actionHost))
        ) {
            return $url;
        }

        throw new InvalidArgumentException('Notification action URL is not trusted.');
    }
}
