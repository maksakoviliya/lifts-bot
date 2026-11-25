<?php

namespace Tests\Feature;

use App\Models\WebCam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EgegeshPushTest extends TestCase
{
    use RefreshDatabase;

    private const IMAGE = 'https://static.egegesh.ru/screenshot/cam-hd/2026/11/25/abc123.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.egegesh.push_token' => 't0ken']);
        Storage::fake();
    }

    private function push(array $data, string $token = 't0ken')
    {
        return $this->post('/egegesh/push', $data, ['Authorization' => "Bearer $token", 'Accept' => 'application/json']);
    }

    private function camPage(): UploadedFile
    {
        $state = json_encode(['show' => ['cam-a' => ['galery' => [['image' => self::IMAGE]]]]]);

        return UploadedFile::fake()->createWithContent('page.html', "<script>window.__REDUX_STATE__ = $state;</script>");
    }

    public function test_rejects_wrong_or_unconfigured_token(): void
    {
        $this->push([], 'wrong')->assertForbidden();

        config(['services.egegesh.push_token' => '']);
        $this->push([], '')->assertForbidden();
    }

    public function test_page_then_image_flow(): void
    {
        $cam = WebCam::query()->create(['aliace' => 'cam-a', 'name' => 'A', 'sector' => 'E', 'work' => 1]);

        // new screenshot url -> the script is asked for the picture
        $this->push(['alias' => 'cam-a', 'page' => $this->camPage()])->assertOk()->assertContent(self::IMAGE . "\n");
        $this->assertSame(self::IMAGE, $cam->fresh()->screenshot);

        $this->push(['alias' => 'cam-a', 'image' => UploadedFile::fake()->image('image.jpg')])->assertOk();
        Storage::assertExists("webcams/{$cam->id}-abc123.jpg");

        // same screenshot again -> nothing more to fetch
        $this->push(['alias' => 'cam-a', 'page' => $this->camPage()])->assertOk()->assertContent('');
    }

    public function test_rejects_non_image_upload(): void
    {
        WebCam::query()->create(['aliace' => 'cam-a', 'name' => 'A', 'sector' => 'E', 'work' => 1, 'screenshot' => self::IMAGE]);

        $this->push(['alias' => 'cam-a', 'image' => UploadedFile::fake()->createWithContent('x.html', '<?php echo 1;')])
            ->assertUnprocessable();
    }
}
