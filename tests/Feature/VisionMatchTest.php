<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\VisionSession;
use App\Models\VisionStep;
use App\Services\VisionMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Seeders\TestSeeder;
use Tests\TestCase;

/**
 * Find My Frames: options never dead-end, matches follow the face shape and
 * preferences with reasons, and only the face shape and size are stored.
 */
class VisionMatchTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = TestSeeder::class;

    public function test_page_offers_only_options_some_frame_matches(): void
    {
        VisionStep::query()->delete();
        VisionStep::query()->create(['key' => 'shape', 'title' => 'Frame shape', 'options' => 'Aviator Prism · Cat-Eye Flair · Architectural Square', 'is_required' => false, 'sort_order' => 1]);
        VisionStep::query()->create(['key' => 'finish', 'title' => 'Finish', 'options' => 'Obsidian · Hot Pink', 'is_required' => false, 'sort_order' => 2]);

        $steps = collect($this->get('/visionmatch')->assertOk()->viewData('steps'))->keyBy('key');

        // No cat-eye frame in the catalog, so that option is hidden.
        $this->assertSame(['Aviator Prism', 'Architectural Square'], array_column($steps['shape']['options'], 'label'));
        $this->assertSame(['aviator'], $steps['shape']['options'][0]['families']);
        $this->assertSame(['Obsidian', 'Hot Pink'], array_column($steps['finish']['options'], 'label'), 'Unrecognised finish names map to grey; obsidian (black) exists.');
    }

    public function test_matches_favour_frames_that_suit_the_face_shape(): void
    {
        // Panto and aviator frames both suit a square face; equal scores go to the more viewed frame.
        Product::query()->where('slug', 'aura-prism')->update(['views' => 99999]);

        $response = $this->postJson('/visionmatch/matches', ['face' => ['shape' => 'square', 'size' => 'M']])->assertOk();

        // Soft curves suit a square face: the rounded panto leads, with the reason why.
        $top = $response->json('matches.0');
        $this->assertSame('aura-prism', $top['slug']);
        $this->assertContains('Soft curves balance a strong jaw', $top['reasons']);
        $this->assertArrayHasKey('frame', $top['live']);
        $this->assertSame(['model', 'frame', 'flip', 'scale', 'offsetY'], array_keys($top['live']));

        // Square frames repeat a square jaw and rank lower.
        $scores = collect($response->json('matches'))->pluck('score', 'slug');
        $this->assertGreaterThan($scores['apex-01'] ?? 0, $scores['aura-prism']);
    }

    public function test_preferences_and_face_shape_combine_with_reasons(): void
    {
        $match = collect($this->postJson('/visionmatch/matches', [
            'preferences' => ['shape' => 'Architectural Square', 'material' => 'Titanium'],
            'face' => ['shape' => 'round'],
        ])->json('matches'))->firstWhere('slug', 'apex-01');

        $this->assertSame([
            'Angular lines add definition to a round face',
            'The architectural square look you chose',
            'In Japanese β-Titanium, as you asked',
        ], $match['reasons']);
        $this->assertSame(99, $match['score']);
    }

    public function test_face_shape_and_size_are_validated(): void
    {
        $this->postJson('/visionmatch/matches', ['face' => ['shape' => 'triangle']])->assertUnprocessable()->assertJsonValidationErrors('face.shape');
        $this->postJson('/visionmatch/matches', ['face' => ['size' => 'XXL']])->assertUnprocessable()->assertJsonValidationErrors('face.size');
    }

    public function test_only_the_face_shape_and_size_are_stored(): void
    {
        $this->postJson('/visionmatch/matches', ['preferences' => ['finish' => 'Obsidian'], 'face' => ['shape' => 'oval', 'size' => 'S']])->assertOk();

        $session = VisionSession::query()->latest('id')->firstOrFail();
        $this->assertSame(['oval', 'S', ['finish' => 'Obsidian']], [$session->face_shape, $session->size, $session->preferences]);
        $this->assertNotNull($session->top_product_id);
    }

    public function test_scores_depend_on_the_frame_not_its_name(): void
    {
        $matcher = app(VisionMatcher::class);
        $a = Product::query()->where('slug', 'apex-01')->firstOrFail();
        $b = $a->replicate(['slug']);
        $b->slug = 'a-much-longer-slug-for-the-same-frame';

        foreach ([null, 'round', 'square'] as $shape) {
            $this->assertSame($matcher->score($a, [], $shape), $matcher->score($b, [], $shape));
        }
    }

    public function test_colour_shape_and_material_families(): void
    {
        $this->assertSame(['black', 'pink', 'gold', 'brown', 'blue', 'grey'], array_map(VisionMatcher::colourFamily(...), ['#111214', '#C98A84', '#C2A47A', '#8A4A24', '#7F9BB5', '#C0C4C8']));
        $this->assertSame(['round', 'panto'], VisionMatcher::shapeFamilies('Classic Rounded Panto'));
        $this->assertSame(['aviator', 'navigator'], VisionMatcher::shapeFamilies('Navigator Aviator'));
        $this->assertSame(['titanium', 'acetate'], VisionMatcher::materialFamilies('Organic Bio-Acetate & Ti'));
        $this->assertSame([], VisionMatcher::materialFamilies('Tinted crystal'), 'A "ti" inside a word is not titanium.');
    }

    public function test_admin_sees_face_shapes_of_recent_scans(): void
    {
        $this->postJson('/visionmatch/matches', ['face' => ['shape' => 'heart']]);
        $admin = User::query()->where('role', User::ROLE_ADMIN)->firstOrFail();

        $shapes = collect($this->actingAs($admin)->get('/admin/visionmatch')->assertOk()->assertSee('Face shapes (30d)')->viewData('faceShapes'))->pluck('count', 'label');
        $this->assertSame(1, $shapes['Heart']);
    }
}
