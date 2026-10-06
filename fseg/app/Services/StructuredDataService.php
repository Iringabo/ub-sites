<?php

namespace App\Services;

/**
 * schema.org JSON-LD describing the faculty and the page being viewed.
 */
class StructuredDataService
{
    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    public function organization(array $settings): array
    {
        $name = trim((string) ($settings['institution.faculty_name'] ?? '')) ?: trim((string) ($settings['institution.short_name'] ?? ''));
        $data = [
            '@type' => 'CollegeOrUniversity',
            '@id'   => site_url('/') . '#organization',
            'name'  => $name,
            'url'   => site_url('/'),
        ];

        $short = trim((string) ($settings['institution.short_name'] ?? ''));
        if ($short !== '' && $short !== $name) {
            $data['alternateName'] = $short;
        }

        $logo = trim((string) ($settings['assets.logo'] ?? ''));
        if ($logo !== '') {
            $data['logo'] = site_media_url($logo);
        }

        $email = trim((string) ($settings['contact.email'] ?? ''));
        if ($email !== '') {
            $data['email'] = $email;
        }

        $phone = trim((string) ($settings['contact.phone'] ?? ''));
        if ($phone !== '') {
            $data['telephone'] = $phone;
        }

        $address = array_filter([
            '@type'           => 'PostalAddress',
            'streetAddress'   => trim((string) ($settings['contact.address_line'] ?? '')),
            'addressLocality' => trim((string) ($settings['contact.address_commune'] ?? '')),
            'addressRegion'   => trim((string) ($settings['contact.address_province'] ?? '')),
            'addressCountry'  => trim((string) ($settings['contact.address_country'] ?? '')),
        ], static fn (string $value): bool => $value !== '');
        if (count($address) > 1) {
            $data['address'] = $address;
        }

        $university = trim((string) ($settings['institution.university'] ?? ''));
        if ($university !== '') {
            $parent = ['@type' => 'CollegeOrUniversity', 'name' => $university];
            $universityUrl = trim((string) ($settings['institution.university_url'] ?? ''));
            if ($universityUrl !== '') {
                $parent['url'] = $universityUrl;
            }
            $data['parentOrganization'] = $parent;
        }

        $social = $settings['social.links'] ?? [];
        if (is_string($social)) {
            $social = json_decode($social, true);
        }
        if (is_array($social)) {
            $links = array_values(array_filter(array_map('strval', $social), static fn (string $url): bool => str_starts_with($url, 'https://')));
            if ($links !== []) {
                $data['sameAs'] = $links;
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function course(object $programme, ?string $language = null): array
    {
        $data = [
            '@type'       => 'Course',
            'name'        => (string) $programme->title,
            'description' => trim((string) ($programme->summary ?? '')),
            'url'         => site_url('formations/' . $programme->slug),
            'provider'    => ['@id' => site_url('/') . '#organization'],
            'educationalCredentialAwarded' => site_level_label((string) $programme->level),
        ];

        if ($language !== null && trim($language) !== '') {
            $data['inLanguage'] = trim($language);
        }

        if (! empty($programme->credits)) {
            $data['numberOfCredits'] = (int) $programme->credits;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function event(object $post): array
    {
        $data = [
            '@type'               => 'Event',
            'name'                => (string) $post->title,
            'url'                 => site_url('actualites/' . $post->slug),
            'eventStatus'         => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'organizer'           => ['@id' => site_url('/') . '#organization'],
        ];

        $start = $this->isoDate($post->event_starts_at ?? null);
        if ($start !== null) {
            $data['startDate'] = $start;
        }
        $end = $this->isoDate($post->event_ends_at ?? null);
        if ($end !== null) {
            $data['endDate'] = $end;
        }

        $description = trim((string) ($post->excerpt ?? ''));
        if ($description !== '') {
            $data['description'] = $description;
        }

        $location = trim((string) ($post->event_location ?? ''));
        $data['location'] = ['@type' => 'Place', 'name' => $location !== '' ? $location : (string) ($post->title ?? '')];

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function person(object $member): array
    {
        $data = [
            '@type'    => 'Person',
            'name'     => (string) $member->name,
            'url'      => site_url('corps-enseignant/' . $member->slug),
            'worksFor' => ['@id' => site_url('/') . '#organization'],
        ];

        foreach (['grade' => 'honorificPrefix', 'role' => 'jobTitle', 'email' => 'email'] as $field => $property) {
            $value = trim((string) ($member->{$field} ?? ''));
            if ($value !== '') {
                $data[$property] = $value;
            }
        }

        $specialty = trim((string) ($member->specialty ?? ''));
        if ($specialty !== '') {
            $data['knowsAbout'] = $specialty;
        }

        $photo = site_person_media($member->photo ?? null);
        if ($photo !== null) {
            $data['image'] = site_media_url($photo);
        }

        return $data;
    }

    /**
     * @param iterable<object> $items
     *
     * @return array<string, mixed>
     */
    public function faqPage(iterable $items): array
    {
        $questions = [];
        foreach ($items as $item) {
            $questions[] = [
                '@type'          => 'Question',
                'name'           => (string) $item->question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string) $item->answer],
            ];
        }

        return ['@type' => 'FAQPage', 'mainEntity' => $questions];
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    public function render(array $nodes): string
    {
        $json = json_encode(
            ['@context' => 'https://schema.org', '@graph' => array_values($nodes)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP,
        );

        return $json === false ? '{}' : $json;
    }

    private function isoDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : date(DATE_ATOM, $timestamp);
    }
}
