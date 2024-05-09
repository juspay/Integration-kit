using System.Collections.Generic;
using Newtonsoft.Json;

namespace SmartGatewayDotnetBackendApiKeyKit {
    public class Utils {
        public static Dictionary<string, string> FlattenJson(dynamic jsonObject)
        {
            jsonObject = JsonConvert.DeserializeObject<Dictionary<string, object>>(JsonConvert.SerializeObject(jsonObject));
            Dictionary<string, string> flattenedDict = new Dictionary<string, string>();

            foreach (var kvp in jsonObject)
            {
                string value = JsonConvert.SerializeObject(kvp.Value);
                flattenedDict[kvp.Key] = value;
            }

            return flattenedDict;
        }

        public static string StrigifyJson(dynamic jsonObject) {
            return JsonConvert.SerializeObject(jsonObject);
        }
    }
}