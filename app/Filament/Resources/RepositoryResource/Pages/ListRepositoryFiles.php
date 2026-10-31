<?php

declare(strict_types=1);

namespace App\Filament\Resources\RepositoryResource\Pages;

use App\Filament\Resources\RepositoryResource;
use Filament\Resources\Pages\ListRecords;

class ListRepositoryFiles extends ListRecords
{
    protected static string $resource = RepositoryResource::class;

    protected ?string $subheading = 'Every uploaded manuscript with its summary, ready to download for cataloguing or publication. The public repository shows summaries only.';
}
