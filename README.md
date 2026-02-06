# KanBanBuilder component for [MoonShine Laravel admin panel](https://moonshine-laravel.com)

**KanBanBuilder** is a specialized UI component for the MoonShine Laravel admin panel. It extends the functionality of the standard `CardsBuilder`, transforming a static grid of cards into a dynamic, organized **KanBan Board**.

<picture>
    <img alt="Queue Dashboard" src="./art/screenshot.png">
</picture>


## Usage

To modify the main IndexPage component,

```php

use DissNik\MoonShineKanBanBuilder\Components\KanBanBuilder;
use MoonShine\Contracts\UI\ComponentContract;

public function modifyListComponent(ComponentContract $component): ComponentContract
{
    $resource = $this->getResource();

    return KanBanBuilder::make()
        ->title('title')
        ->content(fn(Lead $lead) =>
            Flex::make([
                Flex::make([
                    $lead->created_at,

                    Thumbnails::make(
                        $lead->responsible
                            ? $lead->responsible->map(fn($user) => $user->avatar_url)->filter()->toArray()
                            : [],
                    ),
                ])
                    ->class('w-full')
                    ->justifyAlign('between'),

                Flex::make([
                    Badge::make((string) count($lead->comments))->icon('chat-bubble-left-right'),
                ])
                    ->class('w-full')
                    ->justifyAlign('start'),
            ])
        )
        ->fields($component->getFields())
        ->name($this->getListComponentName())
        ->url(fn(Lead $lead) => $resource->getFormPageUrl($lead->getKey()))
        ->async()
        ->cast($this->getResource()->getCaster())
        ->buttons($this->getButtons())
        ->items($component->getOriginalItems())
        ->groups(LeadStatus::assignableStatuses())
        ->reorderRoute($this->resource->getAsyncMethodUrl('reorder'));
}
```
