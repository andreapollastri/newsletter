<x-filament-panels::page>
    @if ($this->isBusy())
        @php
            $busyHeading = new \Illuminate\Support\HtmlString(
                '<span x-show="! switching">'.e($this->busyMessage()).'</span>'
                .'<span x-show="switching" x-cloak>'.e(__('Newsletter is switching to the new build…')).'</span>'
            );
        @endphp

        {{--
            Polls while an update is asked for or running, and reloads the page when it is done.
            Meanwhile the application can answer 503 (maintenance) or not at all (switching
            release): those polls are not errors, the banner says so instead of a modal.
        --}}
        <div
            wire:poll.3s="followUpdate"
            x-data="{
                switching: false,
                stopIntercepting: null,
                init() {
                    this.stopIntercepting = this.$wire.$interceptRequest(({ onSuccess, onError, onFailure }) => {
                        onSuccess(() => this.switching = false)
                        onFailure(() => this.switching = true)
                        onError(({ response, preventDefault }) => {
                            if (response.status >= 500) {
                                preventDefault()
                                this.switching = true
                            }
                        })
                    })
                },
                destroy() {
                    this.stopIntercepting?.()
                },
            }"
            role="status"
        >
            <x-filament::callout
                color="info"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedArrowPath"
                :heading="$busyHeading"
                :description="__('This page reloads itself when it is done.')"
            />
        </div>
    @endif

    {{ $this->content }}
</x-filament-panels::page>
