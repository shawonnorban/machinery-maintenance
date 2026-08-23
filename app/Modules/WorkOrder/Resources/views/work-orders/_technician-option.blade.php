{{-- One line of the assignment picker.

     The area is on the label rather than only in the sort order: somebody
     choosing between two names needs to see which of them looks after this
     part of the floor, not merely trust that the list is in a helpful
     order. --}}
<option value="{{ $technician->id }}"
    @selected($workOrder->activeAssignments->contains('technician_id', $technician->id))>
    {{ $technician->name }} ({{ $technician->employee_id }})
    @if ($technician->productionLine)
        — {{ $technician->productionLine->name }}
    @elseif ($technician->department)
        — {{ $technician->department->name }}
    @endif
</option>
