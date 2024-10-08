<?php

namespace App\Http\Controllers;

use App\Jobs\DeleteExportedFile;
use App\Models\Question;
use App\Models\Attachment as AttachmentModel;
use App\Trait\Attachment;
use App\Trait\Log;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Uid\Ulid;

class QuestionController extends Controller
{
    use Log, Attachment;

    /**
    * @OA\Get(
    *      path="/api/questions/",
    *      operationId="questions.index",
    *      security={{"bearer_token":{}}},
    *      description="On this route, we can set '*-order' with 'asc' or 'desc'
    *          and '*-search' with any word, in '*', we can too set specifics DB column to
    *          compare between dates in '*-search' we need set a pipe separing the
    *          values, like 'date1|date2', pipe can be used to set more search
    *          words too, but remember, they will compare using 'AND' in 'WHERE'
    *          clasule, they dynamic values only can't be setted on SwaggerUI.",
    *      tags={"questions"},
    *      summary="Show questions on system",
    *      @OA\Parameter(
    *          description="Business's ID, if used a user with management role, don't set it",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="Maintenance's ID",
    *          in="query",
    *          name="maintenance",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="Rows limit by page",
    *          in="query",
    *          name="limit",
    *          required=false,
    *          @OA\Schema(type="integer"),
    *      ),
    *      @OA\Parameter(
    *          description="Page number",
    *          in="query",
    *          name="page",
    *          required=false,
    *          @OA\Schema(type="integer"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show questions available on maintenance",
    *       ),
    *      @OA\Response(
    *          response=400,
    *          description="Validation failed",
    *       ),
    *      @OA\Response(
    *          response=401,
    *          description="Unauthorized (User don't have permission, access token expired or isn't logged yet)",
    *       ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *     )
    */
    public function index()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            if (!$this->checkUserPermission('question', 'read', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $query = $this->filteredResults(request());
            $limit = (request()->has('limit') ? request()->limit : PHP_INT_MAX);
            $page = (request()->has('page') ? (request()->page - 1) : 0);
            $questions = $query->paginate($limit, ['*'], 'page', $page);

            $this->setAfter(json_encode(['message' => 'Showing questions available']));
            $returnMessage =  response()->json(['message' => 'Showing questions available', 'data' => $questions]);
        }
        catch (UnauthorizedException $ex)
        {
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 500);
        }
        finally
        {
            $this->saveLog();
            return $returnMessage;
        }
    }

    /**
    * @OA\Get(
    *      path="/api/questions/export",
    *      operationId="questions.export",
    *      security={{"bearer_token":{}}},
    *      description="On this route, we can set '*-order' with 'asc' or 'desc'
    *          and '*-search' with any word, in '*', we can too set specifics DB column to
    *          compare between dates in '*-search' we need set a pipe separing the
    *          values, like 'date1|date2', pipe can be used to set more search
    *          words too, but remember, they will compare using 'AND' in 'WHERE'
    *          clasule, they dynamic values only can't be setted on SwaggerUI.",
    *      tags={"questions"},
    *      summary="Export questions on system to a file",
    *      @OA\Parameter(
    *          description="Business's ID, if used a user with management role, don't set it",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="Maintenance's ID",
    *          in="query",
    *          name="maintenance",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="Rows limit by page",
    *          in="query",
    *          name="limit",
    *          required=false,
    *          @OA\Schema(type="integer"),
    *      ),
    *      @OA\Parameter(
    *          description="Page number",
    *          in="query",
    *          name="page",
    *          required=false,
    *          @OA\Schema(type="integer"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Return a link to download exported file",
    *       ),
    *      @OA\Response(
    *          response=400,
    *          description="Validation failed",
    *       ),
    *      @OA\Response(
    *          response=401,
    *          description="Unauthorized (User don't have permission, access token expired or isn't logged yet)",
    *       ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *     )
    */
    public function export()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            if (!$this->checkUserPermission('question', 'read', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $this->setBefore(json_encode(request()->all()));

            $questions = $this->filteredResults(request())->get();

            $path = implode(DIRECTORY_SEPARATOR, ['export']);

            if(!File::isDirectory(Storage::disk('public')->path($path)))
            {
                File::makeDirectory(Storage::disk('public')->path($path), 0755, true, true);
            }

            $filePath = implode(DIRECTORY_SEPARATOR, ['export', 'questions_'.Ulid::generate().'.csv']);
            $file = fopen(Storage::disk('public')->path($filePath), 'w');

            // Add CSV headers
            fputcsv($file, [
                'Nome',
                'Descrição',
                'Data',
                'Status',
                'Observação',
                'Manutenção',
                'Prédio',
                'Negócio',
                'Questão criado em',
                'Questão atualizado em',
            ]);

            foreach ($questions as $question)
            {
                fputcsv($file, [
                    $question->name,
                    $question->description,
                    $question->date,
                    $question->status,
                    $question->observations,
                    $question->maintenance,
                    $question->building,
                    $question->business,
                    $question->created_at,
                    $question->updated_at
                ]);
            }

            fclose($file);

            $timeToExclude = now()->addHours(24);

            DeleteExportedFile::dispatch(Storage::disk('public')->path($filePath))->delay($timeToExclude);

            $this->setAfter(json_encode(['message' => 'Download link available to get questions in CSV file']));
            $returnMessage =  response()->json([
                'message' => 'Download link available to get questions in CSV file',
                'data' => [
                    'url' => Storage::disk('public')->url(str_replace(DIRECTORY_SEPARATOR, '/', $filePath)),
                    'available_until' => $timeToExclude,
                ]
            ]);
        }
        catch (UnauthorizedException $ex)
        {
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 500);
        }
        finally
        {
            $this->saveLog();
            return $returnMessage;
        }
    }

    /**
    * @OA\Get(
    *      path="/api/questions/{id}",
    *      operationId="questions.show",
    *      security={{"bearer_token":{}}},
    *      description="<b>Important:</b><br>
    *          Business's ID need to be setted if user authenticated:<br>
    *          1 - Don't have a management role with permission 'question > read' enabled or;<br>
    *          2 - Have a role with permission 'question > read' enabled in a specific business;<br>
    *          On first case, we can see all management questions, but in second case we can only see info of questions how
    *          is attached on all business.",
    *      tags={"questions"},
    *      summary="Show a specific question info",
    *      @OA\Parameter(
    *          description="Business's ID",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="Question's ID",
    *          in="path",
    *          name="id",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show question info",
    *       ),
    *      @OA\Response(
    *          response=400,
    *          description="Validation failed",
    *       ),
    *      @OA\Response(
    *          response=401,
    *          description="Unauthorized (User don't have permission, access token expired or isn't logged yet)",
    *       ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *     )
    */
    public function show()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            if (!$this->checkUserPermission('question', 'read', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $validator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ) , [
                'id' => 'required|string|exists:questions,id',
            ]);

            if($validator->fails())
            {
                throw new ValidationException($validator);
            }

            $query = Question::query();

            $query->select([
                'questions.name as name',
                'questions.description as description',
                'questions.date as date',
                'questions.status as status',
                'questions.observations as observations',
                'maintenances.name as maintenance',
                'buildings.name as building',
                'businesses.name as business',
                'questions.created_at as created_at',
                'questions.updated_at as updated_at',
            ]);

            $query->leftJoin('maintenances', 'maintenances.id', '=', 'questions.maintenance');
            $query->leftJoin('buildings', 'buildings.id', '=', 'maintenances.building');
            $query->leftJoin('businesses', 'businesses.id', '=', 'buildings.business');
            $query->where('questions.id', '=', request()->id);
            $question = $query->first();

            $query = AttachmentModel::query();

            $query->select([
                'attachments.name as name',
                'attachments.category as category',
                'attachments.type as type',
                'attachments.size as size',
                'attachments.url as url',
            ]);

            $query->where('attachments.question', '=', request()->id);
            $question['attachments'] = $query->get();

            $this->setAfter(json_encode(['message' => 'Showing question available']));
            $returnMessage =  response()->json(['message' => 'Showing question available', 'data' => $question]);
        }
        catch (UnauthorizedException $ex)
        {
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 500);
        }
        finally
        {
            $this->saveLog();
            return $returnMessage;
        }
    }

    /**
    * @OA\Post(
    *      path="/api/questions",
    *      operationId="questions.store",
    *      security={{"bearer_token":{}}},
    *      summary="Create a new question on system",
    *      tags={"questions"},
    *      @OA\Parameter(
    *          description="Business's ID (don't set if you want to create a management role)",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New question's name",
    *          in="query",
    *          name="name",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New question's description",
    *          in="query",
    *          name="description",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New question's date",
    *          in="query",
    *          name="date",
    *          required=true,
    *          @OA\Schema(type="string",format="date"),
    *      ),
    *      @OA\Parameter(
    *          description="New question's status",
    *          in="query",
    *          name="status",
    *          required=false,
    *          @OA\Schema(type="boolean"),
    *      ),
    *      @OA\Parameter(
    *          description="New question's observations",
    *          in="query",
    *          name="observations",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New question's maintenance",
    *          in="query",
    *          name="maintenance",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New question's attachments",
    *          in="query",
    *          name="attachments",
    *          required=false,
    *          @OA\Schema(
    *               type="array",
    *               @OA\Items(
    *                  type="object",
    *                  @OA\Property(
    *                      type="string",
    *                      property="filename",
    *                  ),
    *                  @OA\Property(
    *                       type="string",
    *                       property="content",
    *                  ),
    *               ),
    *           ),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show question created info",
    *       ),
    *      @OA\Response(
    *          response=400,
    *          description="Validation failed",
    *       ),
    *      @OA\Response(
    *          response=401,
    *          description="Unauthorized (User don't have permission, access token expired or isn't logged yet)",
    *       ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *     )
    */
    public function store()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            DB::beginTransaction();

            if (!$this->checkUserPermission('question', 'create', (request()->has('business') ? request()->business : null)) ||
                request()->has('attachments') && !$this->checkUserPermission('attachment', 'create', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $errorBag = new MessageBag();
            $requestTreated = array_merge(
                request()->route()->parameters(),
                request()->all()
            );

            // Question validator
            $questionValidator = Validator::make($requestTreated, [
                'name' => 'required|string',
                'description' => 'required|string',
                'date' => 'required|date',
                'status' => 'sometimes|nullable|boolean',
                'observations' => 'sometimes|nullable|string',
                'maintenance' => 'required|string|exists:maintenances,id',
            ]);

            if ($questionValidator->fails())
            {
                $errorBag->merge($questionValidator->errors());
            }

            if ($errorBag->isNotEmpty())
            {
                $combinedValidator = Validator::make([], []);
                $combinedValidator->errors()->merge($errorBag);
                throw new ValidationException($combinedValidator);
            }

            $question = new Question();
            $question->id = Ulid::generate();
            $question->name = $requestTreated['name'];
            $question->description = $requestTreated['description'];
            $question->date = $requestTreated['date'];
            if (request()->has('status'))
            {
                $question->status = $requestTreated['status'];
            }
            $question->observations = $requestTreated['observations'];
            $question->maintenance = $requestTreated['maintenance'];
            $question->save();

            DB::commit();

            $this->setAfter(json_encode(['message' => 'Successfully question created']));
            $returnMessage =  response()->json(['message' => 'Successfully question created', 'data' => $question]);
        }
        catch (UnauthorizedException $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 500);
        }
        finally
        {
            $this->saveLog();
            return $returnMessage;
        }
    }

    /**
    * @OA\Put(
    *      path="/api/questions/{id}",
    *      operationId="questions.update",
    *      security={{"bearer_token":{}}},
    *      summary="Update a question on system",
    *      tags={"questions"},
    *      @OA\Parameter(
    *          description="Business's ID (don't set if you want to create a management role)",
    *          in="query",
    *          name="business",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="Question's ID",
    *          in="path",
    *          name="id",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New question's name",
    *          in="query",
    *          name="name",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New question's description",
    *          in="query",
    *          name="description",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New question's date",
    *          in="query",
    *          name="date",
    *          required=false,
    *          @OA\Schema(type="string",format="date"),
    *      ),
    *      @OA\Parameter(
    *          description="New question's status",
    *          in="query",
    *          name="status",
    *          required=false,
    *          @OA\Schema(type="boolean"),
    *      ),
    *      @OA\Parameter(
    *          description="New question's observations",
    *          in="query",
    *          name="observations",
    *          required=false,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New question's maintenance",
    *          in="query",
    *          name="maintenance",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Parameter(
    *          description="New question's attachments to add",
    *          in="query",
    *          name="attachments_to_add",
    *          required=false,
    *          @OA\Schema(
    *               type="array",
    *               @OA\Items(
    *                  type="object",
    *                  @OA\Property(
    *                      type="string",
    *                      property="filename",
    *                  ),
    *                  @OA\Property(
    *                       type="string",
    *                       property="content",
    *                  ),
    *               ),
    *           ),
    *      ),
    *      @OA\Parameter(
    *          description="New question's attachments to remove",
    *          in="query",
    *          name="attachments_to_remove",
    *          required=false,
    *          @OA\Schema(
    *               type="array",
    *               @OA\Items(
    *                  type="object",
    *                  @OA\Property(
    *                      type="string",
    *                      property="filename",
    *                  ),
    *               ),
    *           ),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Show question updated info",
    *       ),
    *      @OA\Response(
    *          response=400,
    *          description="Validation failed",
    *       ),
    *      @OA\Response(
    *          response=401,
    *          description="Unauthorized (User don't have permission, access token expired or isn't logged yet)",
    *       ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *     )
    */
    public function update()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            DB::beginTransaction();

            $errorBag = new MessageBag();
            $requestTreated = array_merge(
                request()->route()->parameters(),
                request()->all()
            );

            // Question validator
            $questionValidator = Validator::make($requestTreated, [
                'id' => 'required|string|exists:questions,id',
                'name' => 'sometimes|nullable|string',
                'description' => 'sometimes|nullable|string',
                'date' => 'sometimes|nullable|date',
                'status' => 'sometimes|nullable|numeric',
                'observations' => 'sometimes|nullable|string',
                'maintenance' => 'sometimes|nullable|string|exists:maintenances,id',
            ]);

            if ($questionValidator->fails())
            {
                $errorBag->merge($questionValidator->errors());
            }

            if ($errorBag->isNotEmpty())
            {
                $combinedValidator = Validator::make([], []);
                $combinedValidator->errors()->merge($errorBag);
                throw new ValidationException($combinedValidator);
            }

            $question = Question::find(request()->id);

            if (request()->has('name'))
            {
                $question->name = $requestTreated['name'];
            }
            if (request()->has('description'))
            {
                $question->description = $requestTreated['description'];
            }
            if (request()->has('date'))
            {
                $question->date = $requestTreated['date'];
            }
            if (request()->has('observations'))
            {
                $question->observations = $requestTreated['observations'];
            }
            if (request()->has('status'))
            {
                $question->status = $requestTreated['status'];
            }
            if (request()->has('maintenance'))
            {
                $question->maintenance = $requestTreated['maintenance'];
            }

            $question->save();
            
            // Anexos
            if (request()->has('fiscal') && count(request()->file('fiscal')) > 0){
                $attachments = request()->file('fiscal');
                foreach ($attachments as $file) {
                    // Fazer o upload do arquivo para o storage
                    $path = $file->store($requestTreated['business'].'/'.$requestTreated['maintenance'].'/'.$question->id, 'public');
                    $attachment = new AttachmentModel();
                    $attachment->id = Ulid::generate();
                    $attachment->name = $file->getClientOriginalName();
                    $attachment->category = 'fiscal';
                    $attachment->path = $path;
                    $attachment->url = Storage::url($path);
                    $attachment->type = $file->getClientMimeType();
                    $attachment->size = $file->getSize();
                    $attachment->question = $question->id;
                    $attachment->user = auth()->user()->id;
                    $attachment->status = true;
                    $attachment->save();
                }
            }

            if (request()->has('video') && count(request()->file('video')) > 0){
                $attachments = request()->file('video');
                foreach ($attachments as $file) {
                    // Fazer o upload do arquivo para o storage
                    $path = $file->store($requestTreated['business'].'/'.$requestTreated['maintenance'].'/'.$question->id, 'public');
                    $attachment = new AttachmentModel();
                    $attachment->id = Ulid::generate();
                    $attachment->name = $file->getClientOriginalName();
                    $attachment->category = 'video';
                    $attachment->path = $path;
                    $attachment->url = Storage::url($path);
                    $attachment->type = $file->getClientMimeType();
                    $attachment->size = $file->getSize();
                    $attachment->question = $question->id;
                    $attachment->user = auth()->user()->id;
                    $attachment->status = true;
                    $attachment->save();
                }
            }

            if (request()->has('photo') && count(request()->file('photo')) > 0){
                $attachments = request()->file('photo');
                foreach ($attachments as $file) {
                    // Fazer o upload do arquivo para o storage
                    $path = $file->store($requestTreated['business'].'/'.$requestTreated['maintenance'].'/'.$question->id, 'public');
                    $attachment = new AttachmentModel();
                    $attachment->id = Ulid::generate();
                    $attachment->name = $file->getClientOriginalName();
                    $attachment->category = 'photo';
                    $attachment->path = $path;
                    $attachment->url = Storage::url($path);
                    $attachment->type = $file->getClientMimeType();
                    $attachment->size = $file->getSize();
                    $attachment->question = $question->id;
                    $attachment->user = auth()->user()->id;
                    $attachment->status = true;
                    $attachment->save();
                }
            }

            //Deletar arquivos em filesDeleted
            if (request()->has('filesDeleted') && count(request()->filesDeleted) > 0){
                $attachments = request()->filesDeleted;
                foreach ($attachments as $file) {
                    $attachment = AttachmentModel::find($file);
                    $attachment->delete();
                }
            }

            DB::commit();

            $this->setAfter(json_encode(['message' => 'Successfully question updated']));
            $returnMessage =  response()->json(['message' => 'Successfully question updated', 'data' => $question]);
        }
        catch (UnauthorizedException $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 500);
        }
        finally
        {
            $this->saveLog();
            return $returnMessage;
        }
    }

    /**
    * @OA\Delete(
    *      path="/api/questions/{id}",
    *      operationId="questions.delete",
    *      security={{"bearer_token":{}}},
    *      summary="Delete a specific question on system",
    *      tags={"questions"},
    *      @OA\Parameter(
    *          description="Question's ID to be deleted",
    *          in="path",
    *          name="id",
    *          required=true,
    *          @OA\Schema(type="string"),
    *      ),
    *      @OA\Response(
    *          response=200,
    *          description="Question removed on system",
    *       ),
    *      @OA\Response(
    *          response=400,
    *          description="Validation failed",
    *       ),
    *      @OA\Response(
    *          response=401,
    *          description="Unauthorized (User don't have permission, access token expired or isn't logged yet)",
    *       ),
    *      @OA\Response(
    *          response=500,
    *          description="API internal error",
    *      ),
    *     )
    */
    public function destroy()
    {
        $returnMessage = null;
        $this->initLog(request());

        try
        {
            $this->setBefore(json_encode(request()->all()));

            DB::beginTransaction();

            if (!$this->checkUserPermission('question', 'delete', (request()->has('business') ? request()->business : null)))
            {
                throw new UnauthorizedException('Unauthorized');
            }

            $questionValidator = Validator::make(array_merge(
                request()->route()->parameters(),
                request()->all()
            ), [
                'id' => 'required|string|exists:questions,id',
            ]);

            if ($questionValidator->fails())
            {
                throw new ValidationException($questionValidator);
            }

            $question = Question::find(request()->route()->parameter('id'))->first();

            $maintenance = $question->maintenanceBelongs;
            $building = $maintenance->buildingBelongs;
            $business = $building->businessBelongs;

            $pathSplitted = [
                $business->id,
                $building->id,
                $maintenance->id,
                $question->id,
            ];

            $attachments = AttachmentModel::where('question', '=', $question->id)->get();

            foreach ($attachments as $attachment)
            {
                $this->deleteAttachment($pathSplitted, $attachment->name);
                $attachment->delete();
            }

            $storageFolder = Storage::path(implode(DIRECTORY_SEPARATOR, $pathSplitted));
            if (File::exists($storageFolder))
            {
                File::deleteDirectory($storageFolder);
            }

            $question->delete();

            DB::commit();

            $this->setAfter(json_encode(['message' => 'Successfully question deleted']));
            $returnMessage =  response()->json(['message' => 'Successfully question deleted']);
        }
        catch (UnauthorizedException $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 401);
        }
        catch (ValidationException $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode($ex->errors()));
            $returnMessage = response()->json(['message' => $ex->errors()], 400);
        }
        catch (Exception $ex)
        {
            DB::rollBack();
            $this->setAfter(json_encode(['message' => $ex->getMessage()]));
            $returnMessage = response()->json(['message' => $ex->getMessage()], 500);
        }
        finally
        {
            $this->saveLog();
            return $returnMessage;
        }
    }

    private function filteredResults(Request $request) : Builder
    {
        // Declare your fixed params here
        $defaultKeys = [
            'limit',
            'page',
            'business',
            'maintenance',
        ];

        $columnsToSearch = [];
        $columnsToOrder = [];
        $columnsOperationSearch = [
            'EQUALS' => [
                'id',
                'status',
                'maintenance',
            ],
            'LIKE' => [
                'name',
                'description',
                'observations',
            ],
            'BETWEEN' => [
                'created_at',
                'updated_at',
                'date',
            ],
        ];

        $validator = Validator::make(array_merge(
            $request->route()->parameters(),
            $request->all()
        ) , [
            'limit' => 'sometimes|nullable|numeric|min:20|max:100',
            'page' => 'sometimes|nullable|numeric|min:1',
            'business' => 'sometimes|nullable|string|exists:businesses,id',
            'maintenance' => 'sometimes|nullable|string|exists:maintenances,id',
            // 'dbColumnName-order' => 'asc|desc'
            // 'dbColumnName-search' => 'first_any_string|optional_second_any_string'
            '*' => function ($attribute, $value, $fail) use ($defaultKeys, &$columnsToOrder, &$columnsToSearch, $columnsOperationSearch) {
                if (!in_array($attribute, $defaultKeys))
                {
                    foreach (['-order', '-search'] as $suffix)
                    {
                        if (substr($attribute, -strlen($suffix)) === $suffix)
                        {
                            $columnName = str_replace($suffix, '', $attribute);
                            $operationType = substr($suffix, 1);

                            if (!Schema::hasColumn('questions', $columnName))
                            {
                                $fail('[validation.column]');
                            }

                            switch ($operationType)
                            {
                                case 'order':
                                    if (!in_array($value, ['asc', 'desc']))
                                    {
                                        $fail('[validation.order]');
                                    }
                                    else
                                    {
                                        $columnsToOrder[$columnName] = $value;
                                    }
                                    break;
                                case 'search':
                                    if (in_array($columnName, $columnsOperationSearch['EQUALS']))
                                    {
                                        $columnsToSearch[$columnName]['operation'] = 'EQUALS';
                                    }
                                    else if (in_array($columnName, $columnsOperationSearch['LIKE']))
                                    {
                                        $columnsToSearch[$columnName]['operation'] = 'LIKE';
                                    }
                                    else if (in_array($columnName, $columnsOperationSearch['BETWEEN']))
                                    {
                                        $columnsToSearch[$columnName]['operation'] = 'BETWEEN';
                                    }
                                    else
                                    {
                                        $fail('[validation.search-operation-not-found');
                                    }
                                    $columnsToSearch[$columnName]['values'] = explode('|', $value);
                                    break;
                                default:
                                    break;
                            }
                        }
                    }
                }
            },
        ]);

        if($validator->fails())
        {
            throw new ValidationException($validator);
        }

        $business = ($request->has('business') ? $request->only('business')['business'] : null);
        $maintenance = ($request->has('maintenance') ? $request->only('maintenance')['maintenance'] : null);

        $query = Question::query();
        $query->select([
            'questions.id as id',
            'questions.name as name',
            'questions.description as description',
            'questions.date as date',
            'questions.status as status',
            'questions.observations as observations',
            'maintenances.name as maintenance',
            'buildings.name as building',
            'businesses.name as business',
            'questions.created_at as created_at',
            'questions.updated_at as updated_at',
        ]);
    
        // Adicionar anexos
        $query->with('attachments');

        if (!empty($columnsToOrder))
        {
            foreach ($columnsToOrder as $column => $direction)
            {
                $query->orderBy($column, $direction);
            }
        }
        else
        {
            $query->orderBy('created_at', 'desc');
        }

        $query->leftJoin('maintenances', 'maintenances.id', '=', 'questions.maintenance');
        $query->leftJoin('buildings', 'buildings.id', '=', 'maintenances.building');
        $query->leftJoin('businesses', 'businesses.id', '=', 'buildings.business');

        if(!empty($business))
        {
            $query->where('businesses.id', '=', $business);
        }

        if(!empty($maintenance))
        {
            $query->where('maintenances.id', '=', $maintenance);
        }

        foreach ($columnsToSearch as $column => $whereInfo)
        {
            if ($whereInfo['operation'] == 'BETWEEN')
            {
                if (count($whereInfo['values']) == 1)
                {
                    $query->whereBetween($column, [$whereInfo['values'][0], $whereInfo['values'][0]]);
                }
                else if (count($whereInfo['values']) % 2 == 0)
                {
                    for ($i = 0; $i < count($whereInfo['values']); $i + 2)
                    {
                        $query->whereBetween($column, [$whereInfo['values'][$i], $whereInfo['values'][($i + 1)]]);
                    }
                }
            }
            else if ($whereInfo['operation'] == 'LIKE')
            {
                foreach ($whereInfo['values'] as $value)
                {
                    $query->where($column, 'LIKE', '%'.$value.'%');
                }
            }
            else if ($whereInfo['operation'] == 'EQUALS')
            {
                foreach ($whereInfo['values'] as $value)
                {
                    $query->where($column, '=', $value);
                }
            }
        }

        return $query;
    }
}
