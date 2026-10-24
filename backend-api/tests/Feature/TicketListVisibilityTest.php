<?php
namespace Tests\Feature;
use App\Models\{MaintenanceTicket, User, Vehicle, VehicleCategory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class TicketListVisibilityTest extends TestCase {
    use RefreshDatabase;
    public function test_hiding_is_personal_reversible_and_does_not_change_active_work(): void {
        $admin=User::factory()->create(['role'=>'Admin']);
        $other=User::factory()->create(['role'=>'Admin']);
        $category=VehicleCategory::create(['category_name'=>'Fire Truck']);
        $vehicle=Vehicle::create(['brand'=>'QA','model'=>'Truck','year_model'=>2022,'capacity'=>'6 pax','vehicle_color'=>'Red','current_location'=>'Depot','vehicle_name'=>'QA Truck','plate_number'=>'QA-221','category_id'=>$category->category_id,'status'=>'Under Maintenance','condition'=>'Needs Repair']);
        $ticket=MaintenanceTicket::create(['vehicle_id'=>$vehicle->vehicle_id,'created_by'=>$admin->id,'ticket_title'=>'QA repair','ticket_description'=>'Pump problem','priority'=>'High','status'=>'Active']);
        $item=$ticket->subIssues()->create(['created_by'=>$admin->id,'title'=>'Pump seal','status'=>'Under Repair','repair_type'=>'in_house']);
        Sanctum::actingAs($admin);
        $url="/api/tickets/{$ticket->ticket_id}/list-visibility";
        $this->putJson($url,['hidden'=>true])->assertOk()->assertJsonPath('hidden_from_list',true);
        $this->putJson($url,['hidden'=>true])->assertOk();
        $this->getJson('/api/tickets')->assertJsonPath('0.hidden_from_list',true)->assertJsonPath('0.status','Active');
        $this->getJson("/api/tickets/{$ticket->ticket_id}")->assertOk();
        $this->assertSame('Under Repair',$item->fresh()->status);
        $this->assertSame('Under Maintenance',$vehicle->fresh()->status);
        $this->assertSame('Needs Repair',$vehicle->fresh()->condition);
        Sanctum::actingAs($other);
        $this->getJson('/api/tickets')->assertJsonPath('0.hidden_from_list',false);
        Sanctum::actingAs($admin);
        $this->putJson($url,['hidden'=>'invalid'])->assertUnprocessable();
        $this->putJson($url,['hidden'=>false])->assertOk();
        $this->getJson('/api/tickets')->assertJsonPath('0.hidden_from_list',false);
        $this->assertDatabaseCount('hidden_ticket_lists',0);
        Sanctum::actingAs(User::factory()->create(['role'=>'Custodian']));
        $this->putJson($url,['hidden'=>true])->assertForbidden();
        $this->assertDatabaseCount('hidden_ticket_lists',0);
    }
}
