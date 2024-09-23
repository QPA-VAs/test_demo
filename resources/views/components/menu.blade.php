<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\LeaveRequest;
use Chatify\ChatifyMessenger;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

$user = getAuthenticatedUser();
if (isAdminOrHasAllDataAccess()) {
    $workspaces = Workspace::all()->skip(0)->take(5);
    $total_workspaces = Workspace::all()->count();
} else {
    $workspaces = $user->workspaces;
    $total_workspaces = count($workspaces);
    $workspaces = $user->workspaces->skip(0)->take(5);
}
$current_workspace = Workspace::find(session()->get('workspace_id'));
$current_workspace_title = $current_workspace->title ?? 'No workspace(s) found';

$messenger = new ChatifyMessenger();
$unread = $messenger->totalUnseenMessages();
$pending_todos_count = $user->todos(0)->count();
$ongoing_meetings_count = $user->meetings('ongoing')->count();
$query = LeaveRequest::where('status', 'pending')
    ->where('workspace_id', session()->get('workspace_id'));

if (!is_admin_or_leave_editor()) {
    $query->where('user_id', $user->id);
}
$pendingLeaveRequestsCount = $query->count();

?>
<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme menu-container">
    <div class="app-brand demo">
        <a href="/home" class="app-brand-link">
            <span class="app-brand-logo demo">
                <img src="{{asset($general_settings['full_logo'])}}" width="200px" alt="" />
            </span>
            <!-- <span class="app-brand-text demo menu-text fw-bolder ms-2">Taskify</span> -->
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
            <i class="bx bx-chevron-left bx-sm align-middle"></i>
        </a>
    </div>
    <div class="menu-inner-shadow"></div>
    <ul class="menu-inner py-1">
        <hr class="dropdown-divider" />
        <!-- Dashboard -->
        <li class="menu-item {{ Request::is('home') ? 'active' : '' }}">
            <a href="/home" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-circle text-danger"></i>
                <div><?= get_label('dashboard', 'Dashboard') ?></div>
            </a>
        </li>
        @if ($user->can('manage_projects'))
        <li class="menu-item {{ Request::is('projects') || Request::is('tags/*') || Request::is('projects/*') ? 'active open' : '' }}">
            <a href="javascript:void(0)" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-briefcase-alt-2 text-success"></i>
                <div><?= get_label('clients projects', 'Clients Projects') ?></div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ Request::is('projects') || Request::is('projects/*') && !Request::is('projects/favorite') ? 'active' : '' }}">
                    <a href="/projects" class="menu-link">
                        <div><?= get_label('manage_projects', 'Manage projects') ?></div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is('projects/favorite') ? 'active' : '' }}">
                    <a href="/projects/favorite" class="menu-link">
                        <div><?= get_label('favorite_projects', 'Favorite projects') ?></div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is('tags/*') ? 'active' : '' }}">
                    <a href="/tags/manage" class="menu-link">
                        <div><?= get_label('tags', 'Tags') ?></div>
                    </a>
                </li>
            </ul>
        </li>


        @endif
        @if ($user->can('manage_tasks'))
        <li class="menu-item {{ Request::is('tasks') || Request::is('tasks/*') ? 'active' : '' }}">
            <a href="/tasks" class="menu-link">
                <i class="menu-icon tf-icons bx bx-task text-primary"></i>
                <div><?= get_label('tasklist', 'Tasklist') ?></div>
            </a>
        </li>
        @endif

{{--        @if ($user->can('manage_projects') || $user->can('manage_tasks'))--}}
{{--        <li class="menu-item {{ Request::is('status/manage') ? 'active' : '' }}">--}}
{{--            <a href="/status/manage" class="menu-link">--}}
{{--                <i class='menu-icon tf-icons bx bx-grid-small text-secondary'></i>--}}
{{--                <div><?= get_label('statuses', 'Statuses') ?></div>--}}
{{--            </a>--}}
{{--        </li>--}}
{{--        @endif--}}


        @if ($user->can('manage_users'))
        <li class="menu-item {{ Request::is('users') || Request::is('users/*') ? 'active' : '' }}">
            <a href="/users" class="menu-link">
                <i class="menu-icon tf-icons bx bx-group text-primary"></i>
                <div><?= get_label('va', 'VA') ?></div>
            </a>
        </li>
        @endif
        @if ($user->can('manage_clients'))
        <li class="menu-item {{ Request::is('clients') || Request::is('clients/*') ? 'active' : '' }}">
            <a href="/clients" class="menu-link">
                <i class="menu-icon tf-icons bx bx-group text-warning"></i>
                <div><?= get_label('clients', 'Clients') ?></div>
            </a>
        </li>
        @endif


        @if ($user->can('manage_estimates_invoices') || $user->can('manage_expenses'))
        <li class="menu-item {{ Request::is('estimates-invoices') || Request::is('estimates-invoices/*') || Request::is('taxes') || Request::is('payment-methods') || Request::is('payments') || Request::is('units') || Request::is('items') || Request::is('expenses') || Request::is('expenses/*') ? 'active open' : '' }}">
            <a href="javascript:void(0)" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-box text-success"></i>
                <?= get_label('finance', 'Finance') ?>
            </a>
            <ul class="menu-sub">
                @if ($user->can('manage_expenses'))
                <li class="menu-item {{ Request::is('expenses') || Request::is('expenses/*') ? 'active' : '' }}">
                    <a href="/expenses" class="menu-link">
                        <div><?= get_label('expenses', 'Expenses') ?></div>
                    </a>
                </li>
                @endif

                @if ($user->can('manage_payslips'))
        <li class="menu-item {{ Request::is('payslips') || Request::is('payslips/*') || Request::is('allowances') || Request::is('deductions') ? 'active open' : '' }}">
            <a href="javascript:void(0)" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-box text-warning"></i>
                <?= get_label('payslips', 'Payslips') ?>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ Request::is('payslips') || Request::is('payslips/*') ? 'active' : '' }}">
                    <a href="/payslips" class="menu-link">
                        <div><?= get_label('manage_payslips', 'Manage payslips') ?></div>
                    </a>
                </li>

                <li class="menu-item {{ Request::is('allowances') ? 'active' : '' }}">
                    <a href="/allowances" class="menu-link">
                        <div><?= get_label('allowances', 'Allowances') ?></div>
                    </a>
                </li>

                <li class="menu-item {{ Request::is('deductions') ? 'active' : '' }}">
                    <a href="/deductions" class="menu-link">
                        <div><?= get_label('deductions', 'Deductions') ?></div>
                    </a>
                </li>
            </ul>
        </li>
        @endif

                @if ($user->can('manage_estimates_invoices'))
                <li class="menu-item {{ Request::is('estimates-invoices') || Request::is('estimates-invoices/*') ? 'active' : '' }}">
                    <a href="/estimates-invoices" class="menu-link">
                        <div><?= get_label('etimates_invoices', 'Estimates/Invoices') ?></div>
                    </a>
                </li>

                <li class="menu-item {{ Request::is('payments') ? 'active' : '' }}">
                    <a href="/payments" class="menu-link">
                        <div><?= get_label('payments', 'Payments') ?></div>
                    </a>
                </li>

                <li class="menu-item {{ Request::is('payment-methods') ? 'active' : '' }}">
                    <a href="/payment-methods" class="menu-link">
                        <div><?= get_label('payment_methods', 'Payment methods') ?></div>
                    </a>
                </li>

                <li class="menu-item {{ Request::is('taxes') ? 'active' : '' }}">
                    <a href="/taxes" class="menu-link">
                        <div><?= get_label('taxes', 'Taxes') ?></div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is('units') ? 'active' : '' }}">
                    <a href="/units" class="menu-link">
                        <div><?= get_label('units', 'Units') ?></div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is('items') ? 'active' : '' }}">
                    <a href="/items" class="menu-link">
                        <div><?= get_label('items', 'Items') ?></div>
                    </a>
                </li>
                @endif
            </ul>
        </li>
        @endif




        <li class="menu-item {{ Request::is('notes') || Request::is('notes/*') ? 'active' : '' }}">
            <a href="/notes" class="menu-link">
                <i class='menu-icon tf-icons bx bx-notepad text-primary'></i>
                <div><?= get_label('notes', 'Notes') ?></div>
            </a>
        </li>



        @role('admin')
        <li class="menu-item {{ Request::is('settings') || Request::is('roles/*') || Request::is('settings/*') ? 'active open' : '' }}">
            <a href="javascript:void(0)" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-box text-success"></i>
                <div data-i18n="User interface"><?= get_label('settings', 'Settings') ?></div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ Request::is('settings/general') ? 'active' : '' }}">
                    <a href="/settings/general" class="menu-link">
                        <div><?= get_label('general', 'General') ?></div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is('settings/permission') || Request::is('roles/*') ? 'active' : '' }}">
                    <a href="/settings/permission" class="menu-link">
                        <div><?= get_label('permissions', 'Permissions') ?></div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is('settings/languages') || Request::is('settings/languages/create') ? 'active' : '' }}">
                    <a href="/settings/languages" class="menu-link">
                        <div><?= get_label('languages', 'Languages') ?></div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is('settings/email') ? 'active' : '' }}">
                    <a href="/settings/email" class="menu-link">
                        <div><?= get_label('email', 'Email') ?></div>
                    </a>
                </li>

                </li>


            </ul>
        </li>
        @endrole
    </ul>
</aside>
