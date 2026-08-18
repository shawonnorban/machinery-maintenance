@props(['title', 'description' => null])

{{-- Every empty state names the reason and the next action. "No work orders"
     is unhelpful; "No open work orders in Dhaka Unit 1 this week - create one"
     is not (Frontend 9.4 rule 2). --}}
<div class="text-center py-5">
    <p class="h5 mb-2">{{ $title }}</p>

    @if ($description)
        <p class="text-body-secondary mb-3">{{ $description }}</p>
    @endif

    @if (isset($action))
        <div>{{ $action }}</div>
    @endif
</div>
