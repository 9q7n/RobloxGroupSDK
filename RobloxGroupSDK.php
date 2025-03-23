<?php
// Copyright (c) 2025 META Interactive
// Written by 9q7n.

class RobloxGroupSDK
{
    private string $apiKey;

    /**
     * Initializes the SDK with an API key.
     *
     * @param string $robloxApiKey The Roblox Open Cloud API key for authentication.
     */
    public function __construct(string $robloxApiKey)
    {
        $this->apiKey = $robloxApiKey;
    }

    /**
     * Makes an HTTP request to the Roblox Open Cloud API.
     *
     * @param string $uri The endpoint URI (excluding base URL).
     * @param array $headers Optional headers to send with the request.
     * @param string $method The HTTP method (GET, POST, PATCH).
     * @param array|null $data Optional request body data.
     * @return array An associative array with 'status' and 'body'.
     */
    private function makeRequest(string $uri, $headers = [], $method = 'GET', $data = null)
    {
        $url = "https://apis.roblox.com/cloud/v2/{$uri}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
            "x-api-key: {$this->apiKey}",
            "Content-Type: application/json"
        ], $headers));

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        }

        if ($data)
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'body' => json_decode($response, true)
        ];
    }

    /**
     * Retrieves a list of all roles in a given Roblox group.
     * This function properly handles pagination to fetch all roles.
     *
     * @param int $groupId The ID of the Roblox group.
     * @return array|null An array of roles or null if the request fails.
     */
    public function listGroupRoles(int $groupId): ?array
    {
        $roles = [];
        $pageToken = null;

        do {
            $query = "groups/{$groupId}/roles?maxPageSize=20";
            if ($pageToken) {
                $query .= "&pageToken=" . urlencode($pageToken);
            }

            $response = $this->makeRequest($query);

            if ($response['status'] !== 200 || !isset($response['body']['groupRoles'])) {
                return null; // Failed request or no roles found
            }

            $roles = array_merge($roles, $response['body']['groupRoles']);
            $pageToken = $response['body']['nextPageToken'] ?? null;
        } while ($pageToken); // Continue fetching if more pages exist

        return $roles;
    }

    /**
     * Sets a user's role in a Roblox group using the given Rank ID.
     *
     * @param int $groupId The ID of the Roblox group.
     * @param int $userId The Roblox User ID to update.
     * @param int $rankId The Rank ID (1-255) to assign.
     * @return bool True if the role update was successful, false otherwise.
     */
    public function setUserGroupRole(int $groupId, int $userId, int $rankId): bool
    {
        $roleId = $this->getRoleIdFromRankId($groupId, $rankId);

        if (!$roleId)
            return false; // Role ID not found

        $response = $this->makeRequest("groups/{$groupId}/memberships/{$userId}", [], 'PATCH', [
            "role" => "groups/{$groupId}/roles/{$roleId}"
        ]);

        return $response['status'] === 200; // Returns true if successful
    }

    /**
     * Retrieves the internal Role ID from the Rank ID (1-255).
     * This function handles pagination to fetch all roles.
     *
     * @param int $groupId The ID of the Roblox group.
     * @param int $rankId The rank ID (1-255) to look up.
     * @return int|null The corresponding Role ID, or null if not found.
     */
    public function getRoleIdFromRankId(int $groupId, int $rankId): ?int
    {
        $pageToken = null;

        do {
            $query = "groups/{$groupId}/roles?maxPageSize=20";

            if ($pageToken)
                $query .= "&pageToken=" . urlencode($pageToken);
            $response = $this->makeRequest($query);

            if ($response['status'] !== 200 || !isset($response['body']['groupRoles']))
                return null; // Failed request or no roles found

            foreach ($response['body']['groupRoles'] as $role) {
                if ($role['rank'] == $rankId)
                    return (int) $role['id']; // Found Role ID for given Rank ID
            }

            $pageToken = $response['body']['nextPageToken'] ?? null;
        } while ($pageToken);

        return null; // No matching Rank ID found
    }
}
