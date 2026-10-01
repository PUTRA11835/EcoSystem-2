<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR & General → Letter Templates: letterheads (a header and a footer image)
 * that the system's letters are printed on, and the page's Menu Access slug.
 *
 * Which letters a letterhead is used for is ticked per letterhead; the letters
 * that can be ticked are listed in App\Models\Letterhead::LETTER_TYPES.
 *
 * Starting grant: EC Administrator only, with create / edit / delete. From
 * here on access is maintained in Management → Roles.
 */
return new class extends Migration
{
    private const SLUG = 'general.letter-templates';

    public function up(): void
    {
        Schema::create('letterheads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();

            // Images on the private `local` disk, served through the controller.
            $table->string('header_path')->nullable();
            $table->string('footer_path')->nullable();

            // Keys of Letterhead::LETTER_TYPES this letterhead is printed on.
            $table->json('letter_types')->nullable();
            $table->timestamps();
        });

        MenuRegistrar::register('general', [self::SLUG => 'Letter Templates — Settings'], 77, 'page');
        MenuRegistrar::grantToAdminAndRoles([self::SLUG], [], ['create', 'edit', 'delete']);
    }

    public function down(): void
    {
        MenuRegistrar::remove([self::SLUG]);
        Schema::dropIfExists('letterheads');
    }
};
