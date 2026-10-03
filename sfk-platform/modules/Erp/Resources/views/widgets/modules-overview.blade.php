<x-filament-widgets::widget>
    <x-filament::section heading="モジュール" description="Installed modules (modules/*/module.json)">
        <div style="overflow-x:auto">
            <table style="width:100%; border-collapse:collapse; font-size:0.875rem">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid rgba(127,127,127,.3)">
                        <th style="padding:.4rem .5rem">Module</th>
                        <th style="padding:.4rem .5rem">Type</th>
                        <th style="padding:.4rem .5rem">Version</th>
                        <th style="padding:.4rem .5rem">Status</th>
                        <th style="padding:.4rem .5rem">Description</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($modules as $module)
                        <tr style="border-bottom:1px solid rgba(127,127,127,.15)">
                            <td style="padding:.4rem .5rem"><strong>{{ $module['name'] }}</strong> <code>{{ $module['slug'] }}</code></td>
                            <td style="padding:.4rem .5rem">{{ $module['type'] }}</td>
                            <td style="padding:.4rem .5rem">{{ $module['version'] }}</td>
                            <td style="padding:.4rem .5rem">
                                @if ($module['enabled'])
                                    <x-filament::badge color="success">enabled</x-filament::badge>
                                @else
                                    <x-filament::badge color="gray">disabled</x-filament::badge>
                                    <span style="opacity:.7">{{ $module['reason'] }}</span>
                                @endif
                            </td>
                            <td style="padding:.4rem .5rem">{{ $module['description'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
