<?php

namespace App\Controllers;

use App\Controllers\Concerns\LoadsPublicPages;
use App\Models\PostModel;
use CodeIgniter\HTTP\ResponseInterface;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * « Add to my calendar »: iCalendar export of one published event.
 */
class EventCalendarController extends BaseController
{
    use LoadsPublicPages;

    public function eventIcs(string $slug): ResponseInterface
    {
        $event = $this->visibleEvents()->where('slug', $slug)->first();
        if ($event === null) {
            return $this->notFound(lang('Site.posts.notFound'));
        }

        return $this->calendarResponse([service('contentTranslationService')->record('posts', $event)], $slug . '.ics');
    }

    private function visibleEvents(): PostModel
    {
        return model(PostModel::class, false)
            ->visible()
            ->where('type', 'event')
            ->where('event_starts_at IS NOT NULL', null, false);
    }

    private function timezone(): DateTimeZone
    {
        try {
            return new DateTimeZone(config('App')->appTimezone);
        } catch (Throwable) {
            return new DateTimeZone('UTC');
        }
    }

    /**
     * @param list<object> $events
     */
    private function calendarResponse(array $events, string $filename): ResponseInterface
    {
        $host = parse_url(base_url(), PHP_URL_HOST) ?: 'localhost';
        $name = site_text_or_placeholder($this->siteSettings['institution.faculty_name'] ?? null);
        $timezone = $this->timezone();
        $stamp = gmdate('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//' . $this->icsText($name) . '//Events//' . strtoupper(site_current_locale()),
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->icsText($name),
        ];

        foreach ($events as $event) {
            $start = $this->utc((string) $event->event_starts_at, $timezone);
            if ($start === null) {
                continue;
            }
            $end = $this->utc((string) ($event->event_ends_at ?? ''), $timezone) ?? $start->add(new DateInterval('PT1H'));

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:post-' . (int) $event->id . '@' . $host;
            $lines[] = 'DTSTAMP:' . $stamp;
            $lines[] = 'DTSTART:' . $start->format('Ymd\THis\Z');
            $lines[] = 'DTEND:' . $end->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:' . $this->icsText((string) $event->title);
            if (trim((string) ($event->excerpt ?? '')) !== '') {
                $lines[] = 'DESCRIPTION:' . $this->icsText((string) $event->excerpt);
            }
            if (trim((string) ($event->event_location ?? '')) !== '') {
                $lines[] = 'LOCATION:' . $this->icsText((string) $event->event_location);
            }
            $lines[] = 'URL:' . site_url('actualites/' . $event->slug);
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return $this->response
            ->setContentType('text/calendar', 'UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . preg_replace('/[^a-z0-9.-]/', '', strtolower($filename)) . '"')
            ->setBody(implode("\r\n", array_map($this->foldLine(...), $lines)) . "\r\n");
    }

    private function utc(string $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value, $timezone))->setTimezone(new DateTimeZone('UTC'));
        } catch (Throwable) {
            return null;
        }
    }

    private function icsText(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", trim($value));

        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $value);
    }

    /**
     * RFC 5545: lines longer than 75 octets continue on the next line after a space.
     */
    private function foldLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = [];
        $current = '';
        foreach (mb_str_split($line) as $character) {
            if (strlen($current . $character) > ($folded === [] ? 75 : 74)) {
                $folded[] = $current;
                $current = '';
            }
            $current .= $character;
        }
        $folded[] = $current;

        return implode("\r\n ", $folded);
    }
}
