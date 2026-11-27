<?php

namespace App\Filament\Resources\Section;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Exception;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\Section\CategoryResource\Pages\ListCategories;
use App\Filament\Resources\Section\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\Section\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\Section\CategoryResource\Pages;
use App\Filament\Resources\Section\CategoryResource\RelationManagers;
use App\Models\Category;
use App\Models\Icon;
use App\Models\NexusModel;
use App\Models\SearchBox;
use App\Models\Torrent;
use App\Repositories\SearchBoxRepository;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Section';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('admin.sidebar.category');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('mode')
                    ->options(SearchBox::listModeOptions())
                    ->label(__('label.search_box.label'))
                    ->reactive()
                    ->rules([
                        'required',
                        function (?Category $record) {
                            return function ($attribute, $value, $fail) use ($record) {
                                if (
                                    $record
                                    && $record->children()->exists()
                                    && (int) $value !== (int) $record->mode
                                ) {
                                    $fail('A menu with submenus must remain in the same section.');
                                }
                            };
                        },
                        function () {
                            return function ($attribute, $value, $fail) {
                                //@todo how to get the editing record ?
                                $exists = Torrent::query()->where('category', $value)->exists();
                                do_log("check $attribute: $value torrent if exists: $exists");
//                                if ($exists) {
//                                    $fail("There are torrents belonging to this category that cannot be changed!");
//                                }
                            };
                        }
                    ])
                ,
                Select::make('parent_id')
                    ->options(function (Get $get, ?Category $record): array {
                        $mode = (int) $get('mode');
                        if ($mode <= 0) {
                            return [];
                        }

                        $query = Category::query()
                            ->where('mode', $mode)
                            ->whereNull('parent_id');
                        if ($record) {
                            $query->where('id', '!=', $record->id);
                        }

                        return $query->orderBy('name')->pluck('name', 'id')->toArray();
                    })
                    ->label(__('label.parent_id'))
                    ->placeholder('Top-level menu/category')
                    ->helperText('Leave empty for a top-level menu. Select one to create a submenu.')
                    ->rules([
                        function (Get $get, ?Category $record) {
                            return function ($attribute, $value, $fail) use ($get, $record) {
                                if ($value === null || $value === '') {
                                    return;
                                }
                                $parent = Category::query()->find($value);
                                if (
                                    !$parent
                                    || $parent->parent_id !== null
                                    || (int) $parent->mode !== (int) $get('mode')
                                    || ($record && $record->children()->exists())
                                ) {
                                    $fail('Choose a top-level category from the same section.');
                                }
                            };
                        },
                    ])
                ,
                TextInput::make('name')->required()->label(__('label.search_box.taxonomy.name'))->required(),
                TextInput::make('image')
                    ->label(__('label.search_box.taxonomy.image'))
                    ->helperText(__('label.search_box.taxonomy.image_help'))
                    ->required(fn (Get $get): bool => SearchBox::query()
                        ->whereKey($get('mode'))
                        ->value('name') !== 'None')
                ,
                Select::make('icon_id')
                    ->options([0 => 'None'] + Icon::query()->pluck('name', 'id')->toArray())
                    ->label(__('label.search_box.taxonomy.icon_id'))
                    ->default(0)
                ,
                TextInput::make('class_name')
                    ->label(__('label.search_box.taxonomy.class_name'))
                    ->helperText(__('label.search_box.taxonomy.class_name_help'))
                ,
                TextInput::make('sort_index')
                    ->default(0)
                    ->label(__('label.priority'))
                    ->helperText(__('label.priority_help'))
                ,

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('search_box.name')->label(__('label.search_box.label')),
                TextColumn::make('parent.name')->label(__('label.parent_id')),
                TextColumn::make('name')->label(__('label.search_box.taxonomy.name'))->searchable(),
                TextColumn::make('icon.name')->label(__('label.search_box.taxonomy.icon_id')),
                TextColumn::make('image')->label(__('label.search_box.taxonomy.image')),
                TextColumn::make('class_name')->label(__('label.search_box.taxonomy.class_name')),
                TextColumn::make('sort_index')->label(__('label.priority'))->sortable(),
            ])
            ->defaultSort('sort_index', 'desc')
            ->filters([
                SelectFilter::make('mode')
                    ->options(SearchBox::query()->pluck('name', 'id')->toArray())
                    ->label(__('label.search_box.label'))
                ,
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->using(function (NexusModel $record) {
                    try {
                        $rep = new SearchBoxRepository();
                        $rep->deleteCategory($record->id);
                        clear_category_cache();
                    } catch (Exception $exception) {
                        Filament::notify('danger', $exception->getMessage() ?: class_basename($exception));
                    }
                }),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()->using(function (Collection $records) {
                    try {
                        $rep = new SearchBoxRepository();
                        $rep->deleteCategory($records->pluck('id')->toArray());
                        clear_category_cache();
                    } catch (Exception $exception) {
                        Filament::notify('danger', $exception->getMessage() ?: class_basename($exception));
                    }
                }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
