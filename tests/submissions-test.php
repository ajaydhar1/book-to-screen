<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/submissions/service.php';
require_once __DIR__ . '/../includes/submissions/migrations.php';
require_once __DIR__ . '/../includes/database/LocalSqliteDatabase.php';
require_once __DIR__ . '/../includes/database/SQLiteCloudDatabase.php';

use SQLiteCloud\SQLiteCloudClient;
use SQLiteCloud\SQLiteCloudRowset;

final class MockSubmissionMailer implements SubmissionMailer
{
    public array $messages = [];
    public bool $fail = false;

    public function sendVerification(string $email, string $link): void
    {
        if ($this->fail) {
            throw new SubmissionMailException('Mock email delivery failure.');
        }
        $this->messages[] = ['email' => $email, 'link' => $link];
    }
}

// Runs the actual cloud database/statement adapters against local SQL; never connects.
final class OfflineCloudClient extends SQLiteCloudClient
{
    public function __construct(private PDO $pdo)
    {
    }

    public function execute($command)
    {
        $stmt = $this->pdo->query($command);
        if ($stmt->columnCount() === 0) {
            return true;
        }
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        $result = new SQLiteCloudRowset();
        $result->ncols = $stmt->columnCount();
        $result->nrows = count($rows);
        $result->colname = [];
        for ($i = 0; $i < $result->ncols; $i++) {
            $result->colname[] = $stmt->getColumnMeta($i)['name'];
        }
        $result->data = $rows === [] ? [] : array_merge(...$rows);
        return $result;
    }

    public function disconnect()
    {
    }
}

function fixture_cloud_db(): Database
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $reflection = new ReflectionClass(SQLiteCloudDatabase::class);
    $db = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('client')->setValue($db, new OfflineCloudClient($pdo));
    return $db;
}

function valid_submission(): array
{
    return [
        'isbn' => '0140328726', 'title' => "Sample O'Brien :title", 'authors' => 'Sample Author',
        'genre' => 'Fantasy, Young Adult', 'subjects' => 'Survival, survival, Friendship',
        'pitch' => 'A sample screen story.', 'submitter-name' => 'Sample Reader', 'email' => 'reader@example.com',
        'screen-format' => 'film', 'role' => 'author', 'authorization' => 'unknown',
        'rights-controller' => 'unknown', 'arrangement' => 'none', 'website' => 'https://example.com',
        'consent' => true, 'privacy' => true,
        'provenance' => ['genre' => ['provider' => 'Open Library', 'state' => 'edited']],
    ];
}

if (defined('B2S_SUBMISSION_TEST_HELPERS')) {
    return;
}

$crypto = new SubmissionCrypto(str_repeat('a', 64));
if (($argv[1] ?? '') === '--worker') {
    $db = new LocalSqliteDatabase($argv[2]);
    $db->exec('PRAGMA busy_timeout = 10000');
    $service = new SubmissionService($db, $crypto, new MockSubmissionMailer(), 'http://localhost/author-submissions');
    $service->submit(valid_submission(), str_repeat('c', 64), 'concurrent', 100000);
    echo "worker-ok\n";
    exit;
}

$checks = 0;
function assert_submission(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    $checks++;
}

function expect_submission_exception(string $class, callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Throwable $e) {
        assert_submission($e instanceof $class, $message . ' (' . get_class($e) . ')');
        return;
    }
    assert_submission(false, $message);
}

function mock_token(MockSubmissionMailer $mail): string
{
    parse_str((string) parse_url($mail->messages[count($mail->messages) - 1]['link'], PHP_URL_FRAGMENT), $fragment);
    return $fragment['token'];
}

foreach (['sqlite', 'cloud-offline'] as $driver) {
    $db = $driver === 'sqlite' ? new LocalSqliteDatabase(':memory:') : fixture_cloud_db();
    assert_submission(submission_migrate($db) === [1,2], $driver . ': versions 1 and 2 applied');
    assert_submission(submission_migrate($db) === [], $driver . ': repeat migration no-op');
    expect_submission_exception(PDOException::class, static fn() => $db->exec("INSERT INTO submission_email_tokens(token_hash,submission_id,expires_at,created_at) VALUES ('orphan','missing',1,1)"), 'Token foreign key enforced');
    foreach (['leads','adaptations','tmdb_adaptations'] as $publicTable) {
        $db->exec("CREATE TABLE {$publicTable}(id INTEGER PRIMARY KEY,marker TEXT)");
        $db->exec("INSERT INTO {$publicTable}(marker) VALUES ('unchanged')");
    }
    $db->exec("CREATE TABLE users(id INTEGER PRIMARY KEY,display_name TEXT,role TEXT,is_active INTEGER,must_change_password INTEGER)");
    $db->exec("INSERT INTO users VALUES (1,'Local Admin','admin',1,0),(2,'Editor','editor',1,0),(3,'Inactive','admin',0,0)");
    $mail = new MockSubmissionMailer();
    $service = new SubmissionService($db, $crypto, $mail, 'http://localhost/author-submissions');
    $input = valid_submission();
    $validated = submission_validate($input);
    assert_submission($validated['book']['isbn'] === '9780140328721', 'Canonical ISBN');
    assert_submission($validated['book']['subjects'] === ['Survival','Friendship'], 'Corrected subjects deduplicated');
    foreach (['title','authors','pitch','submitter-name','email','role','authorization','consent','privacy'] as $required) {
        $bad = $input;
        unset($bad[$required]);
        expect_submission_exception(SubmissionInputException::class, static fn() => submission_validate($bad), 'Required field ' . $required);
    }
    foreach ([
        ['title' => ['wrong type']], ['pitch' => str_repeat('x', 1001)], ['email' => "reader@example.com\r\nBcc: victim@example.com"],
        ['role' => 'admin'], ['authorization' => 'verified'], ['isbn' => '9780140328722'],
        ['website' => 'javascript:alert(1)'], ['website' => 'https://user:pass@example.com'], ['subjects' => "\0"],
        ['privacy' => 'true'], ['provenance' => ['genre' => ['provider' => 'Fake', 'state' => 'verified']]],
    ] as $bad) {
        expect_submission_exception(SubmissionInputException::class, static fn() => submission_validate(array_replace($input, $bad)), 'Invalid input rejected');
    }
    $manual = $input;
    $manual['isbn'] = '';
    $manual['genre'] = '';
    $manual['subjects'] = '';
    assert_submission(submission_validate($manual)['book']['isbn'] === null, 'Manual no-genre path valid');
    $input['authority-details'] = 'Hidden old mandate';
    assert_submission(submission_validate($input)['private']['authority-details'] === '', 'Inactive conditional data stripped');
    assert_submission(!submission_csrf_valid([], 'bad'), 'Missing CSRF denied');
    assert_submission(!submission_csrf_valid(['submission_csrf' => 'expected'], ['expected']), 'Array CSRF denied');
    assert_submission(submission_csrf_valid(['submission_csrf' => 'expected'], 'expected'), 'Valid CSRF accepted');
    foreach ([[], ['admin_logged_in' => true,'role' => 'editor','user_id' => 2], ['admin_logged_in' => true,'role' => 'admin','user_id' => 3],
        ['admin_logged_in' => true,'role' => 'admin','user_id' => 2], ['admin_logged_in' => true,'role' => 'admin','user_id' => 1,'must_change_password' => true]] as $session) {
        assert_submission(submission_admin_user($db, $session) === null, 'Unauthorized admin access denied');
    }
    assert_submission(submission_admin_user($db, ['admin_logged_in' => true,'role' => 'admin','user_id' => 1]) !== null, 'Active database-backed admin accepted');
    $receipt = $service->submit($input, str_repeat('1',64), 'test-client', 10000);
    assert_submission(count($mail->messages) === 1, 'One verification email mocked');
    assert_submission($service->queue() === [], 'Unverified submission not in queue');
    $stored = $db->query('SELECT * FROM property_submissions')->fetch(PDO::FETCH_ASSOC);
    assert_submission($stored['authorization_status'] === 'self_declared', 'No rights verified by identity');
    assert_submission(!str_contains($stored['private_payload'], 'reader@example.com') && !str_contains($stored['private_payload'], 'screen story'), 'Private payload encrypted');
    assert_submission($stored['consent_version'] === SUBMISSION_CONSENT_VERSION && (int) $stored['consent_at'] === 10000, 'Versioned consent timestamp');
    $token = mock_token($mail);
    assert_submission(strlen($token) === 64 && strlen($receipt['receipt']) === 64, 'Secure random token and opaque receipt');
    assert_submission($db->query('SELECT token_hash FROM submission_email_tokens')->fetchColumn() === hash('sha256',$token), 'Only verification token hash stored');
    assert_submission(!str_contains(json_encode($mail->messages, JSON_THROW_ON_ERROR), $input['pitch']), 'Mail contains no pitch or rights');
    assert_submission($service->submit($input,str_repeat('1',64),'test-client',10001) === $receipt, 'Idempotent retry returns same receipt');
    assert_submission(count($mail->messages) === 1 && (int) $db->query('SELECT COUNT(*) FROM property_submissions')->fetchColumn() === 1, 'Retry does not insert/send twice');
    expect_submission_exception(SubmissionConflictException::class, static fn() => $service->submit(array_replace($input,['pitch'=>'Changed']),str_repeat('1',64),'test-client',10002), 'Changed payload same key conflicts');
    assert_submission($service->verify($token, 'verify-client', 10003), 'Email verification succeeds');
    assert_submission(!$service->verify($token, 'verify-client', 10004), 'Single-use verification enforced');
    assert_submission(!$service->verify('bad', 'verify-client', 10004), 'Malformed verification rejected generically');
    assert_submission(count($service->queue()) === 1, 'Verified moves to private queue');
    $detail = $service->detail($stored['id']);
    assert_submission($detail['private']['email'] === 'reader@example.com' && $detail['book']['genres'] === ['Fantasy','Young Adult'], 'Admin sees decrypted declarations and corrected genres');
    $service->review($stored['id'],'reviewing',"Internal O'Brien :note",1,1,10005);
    expect_submission_exception(SubmissionInputException::class, static fn() => $service->review($stored['id'],'closed','unauthorized',2,1,10006), 'Service rejects editor reviewer');
    expect_submission_exception(SubmissionConflictException::class, static fn() => $service->review($stored['id'],'closed','stale',1,0,10006), 'Concurrent/stale review conflicts');
    $detail = $service->detail($stored['id']);
    assert_submission($detail['review_status'] === 'reviewing' && (int) $detail['reviewed_by_user_id'] === 1, 'Reviewer attribution saved');
    assert_submission($detail['events'][0]['note'] === "Internal O'Brien :note", 'Encrypted audit note readable only through private service');
    $service->submit($input,str_repeat('2',64),'second-client',10010);
    assert_submission(count($service->detail($stored['id'])['duplicates']) === 1, 'Internal same-property duplicate warning');
    $expiring = mock_token($mail);
    assert_submission(!$service->verify($expiring,'verify-expiry',96410), 'Boundary expiration rejected');
    $mail->fail = true;
    $failed = $service->submit($manual,str_repeat('3',64),'failure-client',20000);
    assert_submission($service->receipt($failed['receipt'])['delivery_status'] === 'failed', 'Recoverable delivery failure persisted');
    $mail->fail = false;
    $service->resend($failed['receipt'],'resend-client',20061);
    $resent = mock_token($mail);
    assert_submission($service->receipt($failed['receipt'])['delivery_status'] === 'sent', 'Resend recovers delivery');
    assert_submission($service->verify($resent,'resend-verify',20062), 'Resent verification succeeds');
    $limited = $service->submit($manual,str_repeat('4',64),'limited-client',30000);
    $oldToken = mock_token($mail);
    for ($i = 1; $i <= 3; $i++) {
        $service->resend($limited['receipt'],'limited-resend',30000+$i*61);
    }
    $messageCount = count($mail->messages);
    $service->resend($limited['receipt'],'limited-resend',30244);
    assert_submission(count($mail->messages) === $messageCount, 'Resends capped at three per submission');
    assert_submission(!$service->verify($oldToken,'old-token',30245), 'Resend invalidates previous token');
    $service->resend(str_repeat('f',64),'unknown-receipt',30245);
    assert_submission(count($mail->messages) === $messageCount, 'Unknown receipt generic no-op');
    for ($i = 0; $i < 2; $i++) submission_rate_limit($db,$crypto,'test','client',1,2,60);
    expect_submission_exception(SubmissionRateException::class, static fn() => submission_rate_limit($db,$crypto,'test','client',1,2,60), 'Rate threshold enforced');
    submission_rate_limit($db,$crypto,'test','client',61,2,60);
    assert_submission(true, 'Rate window resets');
    $ciphertext = $crypto->encrypt('private fixture');
    assert_submission($crypto->decrypt($ciphertext) === 'private fixture', 'Authenticated encryption roundtrip');
    $tampered = base64_decode($ciphertext);
    $tampered[15] = chr(ord($tampered[15]) ^ 1);
    expect_submission_exception(RuntimeException::class, static fn() => $crypto->decrypt(base64_encode($tampered)), 'Tampered private data rejected');
    foreach (['leads','adaptations','tmdb_adaptations'] as $publicTable) {
        assert_submission((int) $db->query("SELECT COUNT(*) FROM {$publicTable}")->fetchColumn() === 1
            && $db->query("SELECT marker FROM {$publicTable}")->fetchColumn() === 'unchanged', 'Public table untouched');
    }
    $stmt = $db->prepare("SELECT ':not_a_binding' AS literal, :value AS bound /* :comment */ -- :another\n");
    $stmt->execute([':value' => "O'Brien :value"]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    assert_submission($result['literal'] === ':not_a_binding' && $result['bound'] === "O'Brien :value", 'Safe adapter binding preserves literals/comments and quoted values');
    $stmt = $db->prepare("SELECT 'it''s :literal' AS ':alias', :value AS \":double\", :value AS `:backtick`, :value AS [:bracket]");
    $stmt->execute(['value' => 'https://example.com:443/story?q=:value']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    assert_submission($result[':alias'] === "it's :literal"
        && $result[':double'] === $result[':backtick'] && $result[':backtick'] === $result[':bracket']
        && $result[':bracket'] === 'https://example.com:443/story?q=:value', 'Quoted identifiers, escaped literals and repeated named parameters preserved');
    $stmt = $db->prepare("SELECT '/* :fake */ -- :fake' AS text, :one AS first, :one_more AS second -- :ignored\r\n/* :ignored */");
    $stmt->execute(['one' => 1, 'one_more' => 2]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    assert_submission($result['text'] === '/* :fake */ -- :fake' && (int) $result['first'] === 1
        && (int) $result['second'] === 2, 'Comment syntax inside strings and parameter-name prefixes preserved');
    $db->exec('CREATE TABLE adapter_regression(id INTEGER PRIMARY KEY, title TEXT, source_url TEXT, status TEXT, active INTEGER, note TEXT)');
    $stmt = $db->prepare('INSERT INTO adapter_regression(title,source_url,status,active,note) VALUES (:title,:url,:status,:active,:note)');
    $stmt->execute(['title' => "Sample O'Brien :title", 'url' => 'https://example.com:443/story',
        'status' => 'pending', 'active' => true, 'note' => null]);
    $adapterId = (int) $db->lastInsertId();
    assert_submission($stmt->rowCount() === 1 && $adapterId > 0, 'Existing-style insert row count and last insert ID');
    $stmt->execute(['title' => 'Second sample', 'url' => '', 'status' => 'ignored', 'active' => false, 'note' => '{"key":":note"}']);
    assert_submission($stmt->rowCount() === 1, 'Repeated statement execution replaces bound values');
    $stmt = $db->prepare("SELECT title,active,note FROM adapter_regression
        WHERE lower(trim(COALESCE(source_url, ''))) = lower(trim(:source_url)) AND status = :status ORDER BY id LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':source_url', 'https://example.com:443/story');
    $stmt->bindValue('status', 'pending');
    $stmt->bindValue(':limit', 10, PDO::PARAM_INT);
    $stmt->bindValue(':offset', 0, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    assert_submission($result['title'] === "Sample O'Brien :title" && (int) $result['active'] === 1
        && $result['note'] === null && $stmt->fetch() === false, 'Research duplicate query, bound pagination, boolean/null and cursor behavior');
    $stmt = $db->prepare('UPDATE adapter_regression SET status = :status WHERE id = :id');
    $stmt->execute(['status' => 'approved', 'id' => $adapterId]);
    assert_submission($stmt->rowCount() === 1, 'Existing-style status update reports affected rows');
    assert_submission($db->beginTransaction(), 'Adapter regression transaction begins');
    $stmt = $db->prepare('DELETE FROM adapter_regression WHERE id = :id');
    $stmt->execute(['id' => $adapterId]);
    assert_submission($stmt->rowCount() === 1 && $db->rollBack(), 'Existing-style deletion and rollback');
    assert_submission((int) $db->query('SELECT COUNT(*) FROM adapter_regression')->fetchColumn() === 2, 'Rollback preserves research-style records');
    $stmt = $db->prepare('SELECT :integer AS number, :flag AS flag, :empty AS empty');
    $stmt->bindValue('integer', 42, PDO::PARAM_INT);
    $stmt->bindValue('flag', false, PDO::PARAM_BOOL);
    $stmt->bindValue('empty', null, PDO::PARAM_NULL);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_NUM);
    assert_submission((int) $result[0] === 42 && (int) $result[1] === 0 && $result[2] === null, 'Explicit binding types and numeric fetch mode');
    $stmt = $db->prepare('SELECT :value AS result');
    $stmt->execute(['value' => 'sample']);
    $result = $stmt->fetch(PDO::FETCH_BOTH);
    assert_submission($result[0] === 'sample' && $result['result'] === 'sample', 'Combined fetch mode preserved');
    expect_submission_exception(RuntimeException::class, static fn() => $crypto->decrypt('bad'), 'Invalid encrypted payload rejected');
    $db->exec("CREATE TRIGGER test_submission_failure BEFORE INSERT ON submission_events
        WHEN NEW.event_type = 'submitted' BEGIN SELECT RAISE(ABORT,'fixture failure'); END");
    $before = (int) $db->query('SELECT COUNT(*) FROM property_submissions')->fetchColumn();
    expect_submission_exception(PDOException::class, static fn() => $service->submit($manual,str_repeat('5',64),'rollback-client',40000), 'Audit failure rolls back submission');
    assert_submission((int) $db->query('SELECT COUNT(*) FROM property_submissions')->fetchColumn() === $before, 'No partial submission on audit failure');
    $db->exec('DROP TRIGGER test_submission_failure');
    $db->exec("UPDATE submission_schema_versions SET checksum = 'corrupt'");
    expect_submission_exception(RuntimeException::class, static fn() => submission_migrate($db), 'Applied migration checksum mismatch rejected');
    echo "PASS driver: {$driver}\n";
}

require_once __DIR__ . '/../vendor/autoload.php';
final class MockPHPMailer extends \PHPMailer\PHPMailer\PHPMailer
{
    public bool $failDelivery = false;
    public int $calls = 0;

    public function send()
    {
        $this->calls++;
        if ($this->failDelivery) {
            throw new \PHPMailer\PHPMailer\Exception('Synthetic SMTP failure');
        }
        return true;
    }
}
$smtpDb = new LocalSqliteDatabase(':memory:');
submission_migrate($smtpDb);
$smtpConfig = [
    'approved' => true, 'test_recipient' => 'owned-test@example.com',
    'host' => 'mail.privateemail.com', 'port' => 465,
    'username' => 'sender@example.com', 'password' => 'synthetic-fixture-not-a-secret',
    'from' => 'sender@example.com', 'hourly_limit' => 2,
];
$mockSmtp = new MockPHPMailer(true);
$smtp = new SmtpSubmissionMailer($smtpConfig,$smtpDb,$crypto,static fn() => $mockSmtp);
expect_submission_exception(RuntimeException::class, static fn() => new SmtpSubmissionMailer(array_replace($smtpConfig,['approved'=>false]),$smtpDb,$crypto), 'SMTP requires explicit approval');
expect_submission_exception(SubmissionMailException::class, static fn() => $smtp->sendVerification('unowned@example.com','http://localhost/author-submissions/verify.php#token=sample'), 'Only owned approved recipient allowed');
$smtp->sendVerification('owned-test@example.com','http://localhost/author-submissions/verify.php#token=sample');
assert_submission($mockSmtp->SMTPAuth && $mockSmtp->SMTPSecure === 'ssl' && $mockSmtp->SMTPDebug === 0 && $mockSmtp->SMTPOptions['ssl']['verify_peer'], 'Authenticated SMTP implicit TLS without debug logging');
assert_submission($mockSmtp->Timeout === 10 && !str_contains($mockSmtp->Body,'pitch'), 'Bounded SMTP and minimal message');
$mockSmtp->failDelivery = true;
expect_submission_exception(SubmissionMailException::class, static fn() => $smtp->sendVerification('owned-test@example.com','http://localhost/author-submissions/verify.php#token=sample'), 'SMTP failure recoverable');
expect_submission_exception(SubmissionMailException::class, static fn() => $smtp->sendVerification('owned-test@example.com','http://localhost/author-submissions/verify.php#token=sample'), 'SMTP account hourly budget enforced');
assert_submission($mockSmtp->calls === 2, 'Budget exhaustion never calls SMTP');
$tlsMock = new MockPHPMailer(true);
$tlsConfig = array_replace($smtpConfig,['port'=>587,'hourly_limit'=>10]);
$tls = new SmtpSubmissionMailer($tlsConfig,$smtpDb,$crypto,static fn() => $tlsMock);
$tls->sendVerification('owned-test@example.com','http://localhost/author-submissions/verify.php#token=sample');
assert_submission($tlsMock->SMTPSecure === 'tls', 'SMTP 587 requires STARTTLS');

$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'b2s-submission-test-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$path = $directory . DIRECTORY_SEPARATOR . 'submissions-local.sqlite';
try {
    $db = new LocalSqliteDatabase($path);
    $db->exec('PRAGMA busy_timeout = 10000');
    submission_migrate($db);
    $workers = [];
    for ($i = 0; $i < 4; $i++) {
        $process = proc_open([PHP_BINARY,'-d','display_errors=0',__FILE__,'--worker',$path],
            [0 => ['pipe','r'],1 => ['pipe','w'],2 => ['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Could not start concurrent test worker.');
        fclose($pipes[0]);
        $workers[] = [$process,$pipes];
    }
    foreach ($workers as [$process,$pipes]) {
        $out = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $exit = proc_close($process);
        assert_submission($exit === 0 && str_contains($out,'worker-ok'), 'Concurrent worker completed: ' . $error);
    }
    assert_submission((int) $db->query('SELECT COUNT(*) FROM property_submissions')->fetchColumn() === 1, 'Four concurrent same-key submissions create one record');
    assert_submission((int) $db->query("SELECT COUNT(*) FROM submission_events WHERE event_type = 'submitted'")->fetchColumn() === 1, 'Concurrent submission has one audit creation');
    assert_submission((int) $db->query('SELECT COUNT(*) FROM submission_email_tokens')->fetchColumn() === 1, 'Concurrent submission issues one token');
    $cleanupEnvironment = getenv();
    $cleanupEnvironment['B2S_SUBMISSIONS_LOCAL'] = '1';
    $cleanupEnvironment['B2S_SUBMISSIONS_DB'] = $path;
    $cleanup = proc_open([PHP_BINARY,'-d','display_errors=0',__DIR__ . '/../scripts/cleanup-submissions-local.php'],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$cleanupEnvironment);
    if(!is_resource($cleanup))throw new RuntimeException('Could not start cleanup test.');
    fclose($pipes[0]);
    $cleanupOutput=stream_get_contents($pipes[1]);
    $cleanupError=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    assert_submission(proc_close($cleanup)===0 && str_contains($cleanupOutput,'Removed local expired submissions: 1'),'Local retention cleanup: '.$cleanupError);
    assert_submission((int)$db->query('SELECT COUNT(*) FROM property_submissions')->fetchColumn()===0
        && (int)$db->query('SELECT COUNT(*) FROM submission_events')->fetchColumn()===0
        && (int)$db->query('SELECT COUNT(*) FROM submission_email_tokens')->fetchColumn()===0,'Retention cascades remove audit and tokens');
    unset($db);
} finally {
    if (is_file($path)) unlink($path);
    rmdir($directory);
}
echo "PASS: {$checks} submission checks; all databases local and all delivery mocked.\n";
