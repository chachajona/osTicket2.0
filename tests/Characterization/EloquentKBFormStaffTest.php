<?php

use App\Models\CannedResponse;
use App\Models\DynamicForm;
use App\Models\DynamicList;
use App\Models\EmailModel;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Filter;
use App\Models\FormEntry;
use App\Models\FormEntryValues;
use App\Models\FormField;
use App\Models\HelpTopic;
use App\Models\LegacyUser;
use App\Models\ListItem;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Sla;
use App\Models\Team;

test('DynamicForm model reads from legacy database', function () {
    $form = DynamicForm::first();

    if ($form === null) {
        $this->markTestSkipped('No forms found in legacy database.');
    }

    expect($form)->toBeInstanceOf(DynamicForm::class);
    expect($form->id)->toBeInt();
});

test('DynamicForm loads fields relation', function () {
    $form = DynamicForm::whereHas('fields')->with('fields')->first();

    if ($form === null) {
        $this->markTestSkipped('No forms with fields found.');
    }

    expect($form->fields)->not->toBeEmpty();
    expect($form->fields->first()->form_id)->toBe($form->id);
});

test('FormField model reads from legacy database', function () {
    $field = FormField::first();

    if ($field === null) {
        $this->markTestSkipped('No form fields found in legacy database.');
    }

    expect($field)->toBeInstanceOf(FormField::class);
    expect($field->id)->toBeInt();
});

test('FormEntry model reads from legacy database', function () {
    $entry = FormEntry::first();

    if ($entry === null) {
        $this->markTestSkipped('No form entries found in legacy database.');
    }

    expect($entry)->toBeInstanceOf(FormEntry::class);
    expect($entry->id)->toBeInt();
});

test('FormEntryValues model reads from legacy database', function () {
    $value = FormEntryValues::first();

    if ($value === null) {
        $this->markTestSkipped('No form entry values found in legacy database.');
    }

    expect($value)->toBeInstanceOf(FormEntryValues::class);
});

test('DynamicList model reads from legacy database', function () {
    $list = DynamicList::first();

    if ($list === null) {
        $this->markTestSkipped('No lists found in legacy database.');
    }

    expect($list)->toBeInstanceOf(DynamicList::class);
    expect($list->id)->toBeInt();
});

test('ListItem loads list relation', function () {
    $item = ListItem::with('list')->first();

    if ($item === null) {
        $this->markTestSkipped('No list items found.');
    }

    expect($item->list)->not->toBeNull();
    expect($item->list->id)->toBe($item->list_id);
});

test('HelpTopic model reads from legacy database', function () {
    $topic = HelpTopic::first();

    if ($topic === null) {
        $this->markTestSkipped('No help topics found in legacy database.');
    }

    expect($topic)->toBeInstanceOf(HelpTopic::class);
    expect($topic->topic_id)->toBeInt();
});

test('HelpTopic loads department relation', function () {
    $topic = HelpTopic::whereNotNull('dept_id')->with('department')->first();

    if ($topic === null) {
        $this->markTestSkipped('No help topics with a department found.');
    }

    expect($topic->department)->not->toBeNull();
    expect($topic->department->id)->toBe($topic->dept_id);
});

test('Faq model reads from legacy database', function () {
    $faq = Faq::first();

    if ($faq === null) {
        $this->markTestSkipped('No FAQs found in legacy database.');
    }

    expect($faq)->toBeInstanceOf(Faq::class);
    expect($faq->faq_id)->toBeInt();
});

test('FaqCategory loads faqs relation', function () {
    $category = FaqCategory::whereHas('faqs')->with('faqs')->first();

    if ($category === null) {
        $this->markTestSkipped('No FAQ categories with FAQs found.');
    }

    expect($category->faqs)->not->toBeEmpty();
    expect($category->faqs->first()->category_id)->toBe($category->category_id);
});

test('Organization model reads from legacy database', function () {
    $org = Organization::first();

    if ($org === null) {
        $this->markTestSkipped('No organizations found in legacy database.');
    }

    expect($org)->toBeInstanceOf(Organization::class);
    expect($org->id)->toBeInt();
});

test('LegacyUser model reads from legacy database', function () {
    $user = LegacyUser::first();

    if ($user === null) {
        $this->markTestSkipped('No legacy users found in legacy database.');
    }

    expect($user)->toBeInstanceOf(LegacyUser::class);
    expect($user->id)->toBeInt();
});

test('LegacyUser loads defaultEmail relation', function () {
    $user = LegacyUser::with('defaultEmail')->first();

    if ($user === null) {
        $this->markTestSkipped('No legacy users found.');
    }

    expect($user->defaultEmail)->not->toBeNull();
    expect($user->defaultEmail->user_id)->toBe($user->id);
});

test('Team model reads from legacy database', function () {
    $team = Team::first();

    if ($team === null) {
        $this->markTestSkipped('No teams found in legacy database.');
    }

    expect($team)->toBeInstanceOf(Team::class);
    expect($team->team_id)->toBeInt();
});

test('Role model reads from legacy database', function () {
    $role = Role::first();

    if ($role === null) {
        $this->markTestSkipped('No roles found in legacy database.');
    }

    expect($role)->toBeInstanceOf(Role::class);
    expect($role->id)->toBeInt();
});

test('Sla model reads from legacy database', function () {
    $sla = Sla::first();

    if ($sla === null) {
        $this->markTestSkipped('No SLAs found in legacy database.');
    }

    expect($sla)->toBeInstanceOf(Sla::class);
    expect($sla->id)->toBeInt();
});

test('Schedule model reads from legacy database', function () {
    $schedule = Schedule::first();

    if ($schedule === null) {
        $this->markTestSkipped('No schedules found in legacy database.');
    }

    expect($schedule)->toBeInstanceOf(Schedule::class);
    expect($schedule->id)->toBeInt();
});

test('EmailModel reads from legacy database', function () {
    $email = EmailModel::first();

    if ($email === null) {
        $this->markTestSkipped('No emails found in legacy database.');
    }

    expect($email)->toBeInstanceOf(EmailModel::class);
    expect($email->email_id)->toBeInt();
});

test('Filter model reads from legacy database', function () {
    $filter = Filter::first();

    if ($filter === null) {
        $this->markTestSkipped('No filters found in legacy database.');
    }

    expect($filter)->toBeInstanceOf(Filter::class);
    expect($filter->id)->toBeInt();
});

test('Filter loads rules relation', function () {
    $filter = Filter::whereHas('rules')->with('rules')->first();

    if ($filter === null) {
        $this->markTestSkipped('No filters with rules found.');
    }

    expect($filter->rules)->not->toBeEmpty();
    expect($filter->rules->first()->filter_id)->toBe($filter->id);
});

test('CannedResponse model reads from legacy database', function () {
    $response = CannedResponse::first();

    if ($response === null) {
        $this->markTestSkipped('No canned responses found in legacy database.');
    }

    expect($response)->toBeInstanceOf(CannedResponse::class);
    expect($response->canned_id)->toBeInt();
});
