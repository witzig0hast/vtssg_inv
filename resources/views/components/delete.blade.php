@props(['action', 'confirm' => 'Wirklich löschen?', 'label' => 'Löschen'])
<form method="post" action="{{ $action }}" data-confirm="{{ $confirm }}" class="inline">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">{{ $label }}</button></form>
