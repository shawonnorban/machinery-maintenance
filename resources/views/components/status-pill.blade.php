@props(['status', 'tone' => 'secondary'])

{{-- Status is never conveyed by colour alone: the pill always carries text.
     A red dot means nothing to a colour-blind technician under factory
     lighting (Frontend 3.3 rule 4). --}}
<span {{ $attributes->merge(['class' => "status-pill text-{$tone} bg-{$tone}-subtle"]) }}>
    {{ $slot->isEmpty() ? $status : $slot }}
</span>
