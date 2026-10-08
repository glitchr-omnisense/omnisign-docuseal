<?php

namespace Omnisign\Docuseal\Action;

use Omnisign\Exception\InvalidConfigException;
use Omnisign\Model\Field;
use Omnisign\Model\FieldType;
use Omnisign\Model\Level;
use Omnisign\Model\Signer;
use Omnisign\Request\Create;
use Omnisign\Request\Request;

/**
 * A submission: from a template of the instance's (POST /submissions,
 * template_id - what DocuSeal's open-source edition offers), or from the
 * envelope's PDFs and fields (POST /submissions/pdf - DocuSeal Pro and its
 * cloud). DocuSeal sends at once: e-mails, or nothing when embedded.
 */
final class CreateAction extends AbstractAction
{
    public function supports(Request $request): bool
    {
        return $request instanceof Create;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Create);
        $envelope = $request->envelope;
        if (Level::SIMPLE !== $envelope->level) {
            throw new InvalidConfigException('The "docuseal" gateway signs at the simple level.');
        }
        if (!$envelope->signers) {
            throw new InvalidConfigException('A DocuSeal submission needs a signer at least.');
        }
        $signers = array_values($envelope->signers);
        $common = array_filter([
            'send_email' => !$envelope->embedded,
            'order' => $envelope->ordered ? 'preserved' : 'random',
            'expire_at' => $envelope->expiresAt?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s').($envelope->expiresAt ? ' UTC' : ''),
            'completed_redirect_url' => $envelope->redirectUrl,
            'message' => null !== $envelope->message ? ['subject' => $envelope->title, 'body' => $envelope->message] : null,
        ], static fn ($v) => null !== $v && '' !== $v);
        if ($envelope->ordered) {
            usort($signers, static fn ($a, $b) => $a->order <=> $b->order);
        }

        if (null !== $envelope->template) {
            $answer = $this->api->json('POST', '/submissions', ['template_id' => (int) $envelope->template, 'submitters' => array_map(fn (Signer $s, int $i) => $this->submitter($s, $envelope->embedded, $s->role ?? (0 === $i ? 'First Party' : throw new InvalidConfigException(\sprintf('On a template, each signer but the first names the role they take: "%s" has none.', $s->key)))), $signers, array_keys($signers))] + $common);
        } else {
            if (!$envelope->documents) {
                throw new InvalidConfigException('A DocuSeal submission is made from a template (Envelope::$template) or from documents.');
            }
            $documents = array_map(fn ($d) => [
                'name' => pathinfo($d->file->filename, \PATHINFO_FILENAME),
                'file' => base64_encode($d->file->content),
                'fields' => array_map(fn (Field $f) => $this->field($f), array_values(array_filter($envelope->fields, static fn (Field $f) => $f->document === $d->key))),
            ], array_values($envelope->documents));
            $answer = $this->api->json('POST', '/submissions/pdf', ['name' => $envelope->title, 'documents' => $documents, 'submitters' => array_map(fn (Signer $s) => $this->submitter($s, $envelope->embedded, $s->role ?? $s->key), $signers)] + $common);
        }

        $request->setResult($this->read($envelope, $answer));
    }

    /** @return array<string, mixed> */
    private function submitter(Signer $signer, bool $embedded, string $role): array
    {
        return array_filter([
            'role' => $role,
            'email' => $signer->email,
            'name' => $signer->name,
            'phone' => $signer->phone,
            'external_id' => $signer->key,
            'send_email' => !$embedded,
            'require_email_2fa' => 'email' === $signer->authentication ? true : null,
            'require_phone_2fa' => 'sms' === $signer->authentication ? true : null,
        ], static fn ($v) => null !== $v);
    }

    /** @return array<string, mixed> */
    private function field(Field $field): array
    {
        return [
            'name' => $field->label ?? $field->type->value.'-'.$field->signer.'-'.$field->page,
            'type' => match ($field->type) {
                FieldType::MENTION => 'text',
                default => $field->type->value,
            },
            'role' => $field->signer,
            'required' => true,
            'areas' => [['x' => $field->x, 'y' => $field->y, 'w' => $field->width ?? 200, 'h' => $field->height ?? 60, 'page' => $field->page]],
        ];
    }
}
